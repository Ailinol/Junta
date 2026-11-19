<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.enabled' => true, 'services.openai.key' => 'forecast-secret', 'services.openai.model' => 'gpt-6.1-terra']);
});

/** @return array{User,Campaign} */
function campaignForecastFixture(): array
{
    $owner = User::factory()->create(['is_organizer' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $owner->id, 'target_quantity' => 100, 'created_at' => now()->subDays(2), 'deadline' => now()->addDays(2)]);
    foreach ([[10, 36], [15, 12], [20, 6]] as [$quantity, $hours]) {
        Participation::factory()->create(['campaign_id' => $campaign->id, 'quantity' => $quantity, 'created_at' => now()->subHours($hours), 'notes' => 'PRIVATE-PARTICIPANT-NOTE']);
    }

    return [$owner, $campaign];
}

/** @param array<string,mixed> $changes
 * @return array<string,mixed>
 */
function campaignForecastResponse(array $changes = []): array
{
    return ['model' => 'gpt-6.1-terra', 'status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'status' => 'completed', 'content' => [['type' => 'output_text', 'text' => json_encode([...['probability' => 0.7, 'expectedQuantity' => 120, 'lowerQuantity' => 70, 'upperQuantity' => 160], ...$changes], JSON_THROW_ON_ERROR)]]]]];
}

test('organizers receive a bounded forecast from server aggregates without changing business records', function () {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    Participation::factory()->create(['campaign_id' => $campaign->id, 'status' => 'cancelada', 'quantity' => 1000]);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(campaignForecastResponse())]);

    $response = $this->actingAs($owner)->postJson(route('ai.forecast', $campaign), ['currentQuantity' => 99999, 'probability' => 1])
        ->assertOk()->assertJsonPath('state', 'estimated')->assertJsonPath('probabilityPercent', 70)->assertJsonPath('expectedQuantity', 120)
        ->assertJsonPath('features.currentQuantity', 45)->assertJsonPath('features.participantCount', 3)->assertJsonPath('features.averageUnitsPerDay', 22.5)
        ->assertJsonPath('features.recentUnitsPerDay', 35)->assertJsonPath('features.previousUnitsPerDay', 10)->assertJsonPath('features.linearProjection', 90)
        ->assertJsonPath('features.remainingHours', 48)->assertJsonPath('expiresAt', now()->addMinutes(5)->toIso8601String());

    Http::assertSent(function (Request $request): bool {
        $state = json_decode($request['input'][1]['content'], true, 64, JSON_THROW_ON_ERROR);

        return $state['currentQuantity'] === 45 && $state['participantCount'] === 3
            && $state['category'] === 'construcao' && $state['location'] === 'Maputo'
            && ! str_contains($request->body(), 'PRIVATE-PARTICIPANT-NOTE') && ! array_key_exists('user_id', $state)
            && $request['store'] === false && $request['text']['format']['schema']['additionalProperties'] === false;
    });
    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'status' => 'ativa', 'target_quantity' => 100]);
    $this->assertDatabaseCount('participations', 4);
    $this->assertDatabaseHas('audit_entries', ['id' => $response->json('analysisId'), 'action' => 'ai.review.created']);
    expect(AuditEntry::findOrFail($response->json('analysisId'))->changes['kind'])->toBe('forecast');
});

test('forecast rejects guests and other campaign owners before sending aggregate data', function () {
    [$owner, $campaign] = campaignForecastFixture();
    $this->postJson(route('ai.forecast', $campaign))->assertUnauthorized();
    $other = User::factory()->create(['is_organizer' => true]);
    $this->actingAs($other)->postJson(route('ai.forecast', $campaign))->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson(route('ai.forecast', $campaign))->assertForbidden();

    Http::assertNothingSent();
    $this->assertDatabaseCount('audit_entries', 0);
});

test('sparse campaigns abstain from percentages and avoid billable requests', function (int $hours, int $participants) {
    $this->freezeTime();
    $owner = User::factory()->create(['is_organizer' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $owner->id, 'created_at' => now()->subHours($hours)]);
    Participation::factory()->count($participants)->create(['campaign_id' => $campaign->id, 'quantity' => 1]);

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertOk()->assertJsonPath('state', 'insufficient_data')->assertJsonPath('probabilityPercent', null)->assertJsonPath('expectedQuantity', null);

    Http::assertNothingSent();
})->with(['new campaign' => [2, 3], 'few participants' => [48, 2], 'no participants' => [48, 0]]);

test('publication time prevents draft age from becoming false observation time', function () {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    AuditEntry::factory()->create(['subject_type' => 'Campaign', 'subject_id' => $campaign->id, 'action' => 'campaign.published', 'created_at' => now()->subHours(2)]);

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertOk()->assertJsonPath('state', 'insufficient_data')->assertJsonPath('features.elapsedHours', 2);

    Http::assertNothingSent();
});

test('reached targets report an observation instead of a fabricated probability', function () {
    [$owner, $campaign] = campaignForecastFixture();
    $campaign->update(['target_quantity' => 40]);

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertOk()->assertJsonPath('state', 'target_reached')->assertJsonPath('probabilityPercent', null);

    Http::assertNothingSent();
});

test('closed interest cannot receive a future prediction', function (array $attributes) {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    $campaign->forceFill($attributes === [] ? ['deadline' => now()] : $attributes)->save();

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertOk()->assertJsonPath('state', 'closed')->assertJsonPath('probabilityPercent', null);

    Http::assertNothingSent();
})->with(['deadline reached' => [[]], 'draft' => [['status' => 'rascunho']], 'payment' => [['purchase_phase' => 'payment']], 'cancelled' => [['status' => 'cancelada']]]);

test('historical context is limited to earlier comparable campaigns and does not invent a success rate', function () {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    $past = Campaign::factory()->create(['category' => $campaign->category, 'unit' => $campaign->unit, 'deadline' => now()->subDays(2), 'created_at' => now()->subDays(5), 'target_quantity' => 100]);
    Participation::factory()->create(['campaign_id' => $past->id, 'quantity' => 60, 'created_at' => now()->subDays(3)]);
    Participation::factory()->create(['campaign_id' => $past->id, 'quantity' => 900, 'created_at' => now()->subDay()]);
    Campaign::factory()->create(['category' => 'outro', 'deadline' => now()->subDay()]);
    Campaign::factory()->create(['unit' => 'tonelada', 'deadline' => now()->subDay()]);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(campaignForecastResponse())]);

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertOk()->assertJsonPath('features.historicalContext.campaignCount', 1)
        ->assertJsonPath('features.historicalContext.sameLocationCount', 1)->assertJsonPath('features.historicalContext.averageRetainedTargetRatio', 0.6)
        ->assertJsonMissingPath('features.historicalContext.successRate');

    Http::assertSentCount(1);
});

test('invalid predictions return 503 and are never recorded as an analysis', function (array $invalid) {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(campaignForecastResponse($invalid))]);

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertServiceUnavailable()->assertJsonMissingPath('probabilityPercent');

    $this->assertDatabaseCount('audit_entries', 0);
    Http::assertSentCount(1);
})->with(['certainty' => [['probability' => 1]], 'negative probability' => [['probability' => -0.1]], 'below current demand' => [['lowerQuantity' => 10]],
    'reversed interval' => [['upperQuantity' => 100]], 'missing expected quantity' => [['expectedQuantity' => null]], 'extra action' => [['selectedProposalId' => 999]], 'string quantity' => [['expectedQuantity' => '120']]]);

test('provider connection failure preserves data and never leaks secrets or retries automatically', function () {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    Http::fake(['https://api.openai.com/v1/responses' => Http::failedConnection()]);

    $response = $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertServiceUnavailable()->assertJsonMissingPath('expectedQuantity');

    expect($response->getContent())->not->toContain('forecast-secret');
    $this->assertDatabaseCount('audit_entries', 0);
    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'status' => 'ativa']);
    Http::assertSentCount(1);
});

test('a prediction expires at the interest deadline when it is less than five minutes away', function () {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    $campaign->update(['deadline' => now()->addMinute()]);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(campaignForecastResponse())]);

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertOk()->assertJsonPath('expiresAt', now()->addMinute()->toIso8601String());

    Http::assertSentCount(1);
});

test('participation changes during inference return 422 instead of recording a stale prediction', function () {
    $this->freezeTime();
    [$owner, $campaign] = campaignForecastFixture();
    Http::fake(['https://api.openai.com/v1/responses' => function () use ($campaign) {
        $campaign->participations()->firstOrFail()->update(['quantity' => 25]);

        return Http::response(campaignForecastResponse());
    }]);

    $this->actingAs($owner)->postJson(route('ai.forecast', $campaign))->assertUnprocessable()->assertJsonValidationErrors('forecast');

    $this->assertDatabaseCount('audit_entries', 0);
    Http::assertSentCount(1);
});

test('the organizer summary exposes a changing forecast version without contacting OpenAI', function () {
    $this->freezeTime();
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    [$owner, $campaign] = campaignForecastFixture();
    $first = $this->actingAs($owner)->get(route('organizador.campanhas.show', $campaign->id))
        ->assertOk()->inertiaProps('organizerCampaign.forecastVersion');
    $this->travel(1)->seconds();
    $campaign->participations()->firstOrFail()->update(['quantity' => 11]);

    $next = $this->get(route('organizador.campanhas.show', $campaign->id))->assertOk()->inertiaProps('organizerCampaign.forecastVersion');

    expect($first)->toBeString()->toHaveLength(64);
    expect($next)->not->toBe($first);
    Http::assertNothingSent();
});
