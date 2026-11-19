<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../OpenAiTestHelpers.php';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.enabled' => true, 'services.openai.key' => 'test-key', 'services.openai.model' => 'gpt-6.1-terra']);
});

/** @return array{User,Campaign,Proposal,Proposal} */
function aiComparisonFixture(): array
{
    $owner = User::factory()->create(['is_organizer' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $owner->id, 'status' => 'em_avaliacao', 'delivery_mode' => 'entrega', 'target_quantity' => 10]);
    Participation::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 10, 'status' => 'pendente']);
    Participation::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 1000, 'status' => 'cancelada']);
    $attributes = ['campaign_id' => $campaign->id, 'available_quantity' => 20, 'minimum_quantity' => 5, 'transport_included' => true, 'transport_unit_cents' => 0, 'reserved_until' => now()->addDays(4), 'valid_until' => now()->addDays(5), 'delivery_mode' => 'entrega', 'delivery_days' => 4];
    $cheap = Proposal::factory()->create([...$attributes, 'supplier_id' => Supplier::factory()->create(['verified' => true])->id, 'unit_price' => '100.01']);
    $fast = Proposal::factory()->create([...$attributes, 'supplier_id' => Supplier::factory()->create(['verified' => true])->id, 'unit_price' => '120.00', 'delivery_days' => 2]);

    return [$owner, $campaign, $cheap, $fast];
}

/** @return array<string,mixed> */
function aiCampaignDraft(): array
{
    return ['title' => 'Arroz em sacos de 25 kg', 'description' => 'Arroz branco, saco fechado, com origem indicada.', 'unit' => 'saco', 'targetQuantity' => 10, 'deadline' => now()->addDays(5)->toDateString(), 'deliveryMode' => 'entrega'];
}

test('AI is explicitly unavailable without configuration and makes no external request', function () {
    config(['services.openai.key' => null]);
    $organizer = User::factory()->create(['is_organizer' => true]);
    $this->actingAs($organizer)->postJson(route('ai.campaign'), aiCampaignDraft())
        ->assertStatus(503)->assertJsonPath('message', 'A IA ainda não está activada. Pode continuar a revisão manual.');
    Http::assertNothingSent();
    expect(AuditEntry::where('action', 'ai.review.created')->count())->toBe(0);
});

test('campaign review records an advisory analysis without creating or publishing a campaign', function () {
    fakeJuntaOpenAi(['draft' => ['clarity' => 1, 'confidence' => 0.6]]);
    $organizer = User::factory()->create(['is_organizer' => true]);
    $this->actingAs($organizer)->postJson(route('ai.campaign'), aiCampaignDraft())
        ->assertOk()->assertJsonPath('clarity', 50)->assertJsonPath('confidence', 60)->assertJsonCount(2, 'findings');
    expect(Campaign::count())->toBe(0);
    $analysis = AuditEntry::where('action', 'ai.review.created')->sole();
    expect($analysis->changes['kind'])->toBe('campaign')
        ->and($analysis->changes['analysis']['inputHash'])->toHaveLength(64);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-key') && $request['model'] === 'gpt-6.1-terra');
});

test('AI endpoints reject guests consumers and other campaign owners before contacting OpenAI', function () {
    [$owner, $campaign] = aiComparisonFixture();
    $weights = ['weights' => ['cost' => 50, 'delivery' => 25, 'clarity' => 25]];
    $this->postJson(route('ai.compare', $campaign), $weights)->assertUnauthorized();
    $consumer = User::factory()->create();
    $this->actingAs($consumer)->postJson(route('ai.campaign'), aiCampaignDraft())->assertForbidden();
    $this->postJson(route('ai.proposal'), [])->assertForbidden();
    $other = User::factory()->create(['is_organizer' => true]);
    $this->actingAs($other)->postJson(route('ai.compare', $campaign), $weights)->assertForbidden();
    Http::assertNothingSent();
});

test('comparison uses exact cents common demand explicit weights and preserves human selection', function () {
    [$owner, $campaign, $cheap, $fast] = aiComparisonFixture();
    fakeJuntaOpenAi();
    $response = $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 100, 'delivery' => 0, 'clarity' => 0]])
        ->assertOk()->assertJsonPath('quantity', 10)->assertJsonPath('recommendedProposalId', $cheap->id)
        ->assertJsonPath('rows.0.totalCents', 100010)->assertJsonPath('rows.0.score', 100);
    expect($campaign->fresh()->status)->toBe('em_avaliacao')->and($cheap->fresh()->status)->toBe('submetida');
    $this->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 0, 'delivery' => 100, 'clarity' => 0]])
        ->assertOk()->assertJsonPath('recommendedProposalId', $fast->id);
    $analysis = AuditEntry::findOrFail($response->json('analysisId'));
    $this->postJson(route('ai.decision', $analysis), ['decision' => 'dismissed'])->assertOk();
    $this->assertDatabaseHas('audit_entries', ['action' => 'ai.review.dismissed', 'subject_id' => $analysis->id]);
    $this->post(route('organizador.campanhas.seleccionar', [$campaign, $fast]))->assertSessionHasNoErrors();
    expect($fast->fresh()->status)->toBe('aceite');
});

test('missing transport excludes an offer while low AI confidence remains an advisory warning', function () {
    [$owner, $campaign, $cheap, $fast] = aiComparisonFixture();
    $cheap->update(['transport_included' => false, 'transport_unit_cents' => null]);
    fakeJuntaOpenAi(['p'.$fast->id => ['confidence' => 0.4]]);
    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 50, 'delivery' => 25, 'clarity' => 25]])
        ->assertOk()->assertJsonPath('recommendedProposalId', $fast->id)->assertJsonPath('rows.1.totalCents', null)
        ->assertJsonPath('rows.1.productTotalCents', 100010)->assertJsonPath('rows.1.transportTotalCents', null)
        ->assertJsonPath('rows.1.eligible', false)->assertJsonPath('rows.0.eligible', true)
        ->assertJsonCount(1, 'rows.0.warnings')->assertJsonPath('recommendation.status', 'recommended');
});

test('declared transport changes the cheapest offer and reservation expiry limits analysis validity', function () {
    [$owner, $campaign, $cheap, $fast] = aiComparisonFixture();
    $this->freezeTime();
    $expiry = now()->addMinute();
    $cheap->update(['transport_included' => false, 'transport_unit_cents' => 2500, 'reserved_until' => $expiry]);
    fakeJuntaOpenAi();
    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 100, 'delivery' => 0, 'clarity' => 0]])
        ->assertOk()->assertJsonPath('recommendedProposalId', $fast->id)->assertJsonPath('rows.1.totalCents', 125010)
        ->assertJsonPath('expiresAt', $expiry->toIso8601String());
});

test('equivalent offers require a human tie break without claiming a unique best offer', function () {
    [$owner, $campaign, $cheap, $fast] = aiComparisonFixture();
    $fast->update(['unit_price' => $cheap->unit_price, 'delivery_days' => $cheap->delivery_days]);
    fakeJuntaOpenAi();
    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 50, 'delivery' => 25, 'clarity' => 25]])
        ->assertOk()->assertJsonPath('recommendedProposalId', null)->assertJsonPath('rows.0.score', 100)->assertJsonPath('rows.1.score', 100)
        ->assertJsonPath('recommendation.status', 'equivalent');
});

test('ordinary clarity warnings still produce a direct best offer with grounded savings and delivery reasons', function () {
    $this->freezeTime();
    [$owner, $campaign, $cheap, $next] = aiComparisonFixture();
    $campaign->participations()->where('status', 'pendente')->update(['quantity' => 9]);
    $cheap->update(['unit_price' => '650.00', 'delivery_days' => 2]);
    $next->update(['unit_price' => '672.75', 'transport_included' => false, 'transport_unit_cents' => 1500, 'delivery_days' => 3]);
    $third = Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => Supplier::factory()->create(['verified' => true])->id,
        'unit_price' => '695.50', 'available_quantity' => 20, 'minimum_quantity' => 5, 'reserved_until' => now()->addDays(4), 'valid_until' => now()->addDays(5),
        'delivery_mode' => 'entrega', 'delivery_days' => 4, 'transport_included' => true, 'transport_unit_cents' => 0]);
    $evaluations = [];
    foreach ([$cheap, $next, $third] as $offer) {
        $evaluations['p'.$offer->id] = ['clarity' => 1, 'confidence' => 0.9, 'alignment' => 0.6];
    }
    fakeJuntaOpenAi($evaluations);

    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 45, 'delivery' => 20, 'clarity' => 20, 'history' => 15]])
        ->assertOk()->assertJsonPath('recommendedProposalId', $cheap->id)->assertJsonPath('recommendation.status', 'recommended')
        ->assertJsonPath('recommendation.alternativeProposalId', $next->id)->assertJsonPath('recommendation.priceDifferenceCents', 33975)
        ->assertJsonPath('recommendation.message', 'Recomendamos a proposta #'.$cheap->id.' de '.$cheap->supplier->company_name.'. É a melhor opção com os critérios definidos.')
        ->assertJsonPath('rows.0.totalCents', 585000)->assertJsonPath('rows.0.clarity', 50)->assertJsonPath('rows.0.eligible', true)
        ->assertJsonCount(0, 'rows.0.findings')->assertJsonCount(2, 'rows.0.warnings')->assertJsonCount(2, 'recommendation.warnings');

    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'status' => 'em_avaliacao']);
    $this->assertDatabaseHas('proposals', ['id' => $cheap->id, 'status' => 'submetida']);
    Http::assertSent(function (Request $request): bool {
        $state = json_decode($request['input'][1]['content'], true, 64, JSON_THROW_ON_ERROR);

        return collect($state['items'])->every(fn (array $item): bool => $item['respondsToCampaign'] === true && $item['unit'] === 'sacos');
    });
});

test('close scores retain a best recommendation when costs differ rather than suppressing the decision', function () {
    [$owner, $campaign, $cheap, $next] = aiComparisonFixture();
    $next->update(['unit_price' => '100.50']);
    fakeJuntaOpenAi();

    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 100, 'delivery' => 0, 'clarity' => 0]])
        ->assertOk()->assertJsonPath('recommendedProposalId', $cheap->id)->assertJsonPath('recommendation.closeCall', true)
        ->assertJsonPath('recommendation.priceDifferenceCents', 490);

    Http::assertSentCount(1);
});

test('comparison flags expired offers unmet minimum stock and unverified suppliers', function () {
    [$owner, $campaign, $cheap, $fast] = aiComparisonFixture();
    $cheap->update(['valid_until' => now()->subMinute(), 'minimum_quantity' => 15, 'reserved_until' => null]);
    $fast->update(['available_quantity' => 2]);
    $fast->supplier->update(['verified' => false]);
    fakeJuntaOpenAi();
    $response = $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 50, 'delivery' => 25, 'clarity' => 25]])
        ->assertOk()->assertJsonPath('recommendedProposalId', null)->assertJsonPath('rows.0.eligible', false)->assertJsonPath('rows.1.eligible', false);
    expect($response->json('rows.0.findings'))->toHaveCount(3)
        ->and($response->json('rows.1.findings'))->toHaveCount(2);
});

test('supplier review sees only its submitted draft and public campaign specification', function () {
    [$owner, $campaign, $competitor] = aiComparisonFixture();
    $competitor->update(['notes' => 'SEGREDO-CONCORRENTE']);
    $supplierUser = User::factory()->create();
    Supplier::factory()->create(['user_id' => $supplierUser->id, 'verified' => true]);
    fakeJuntaOpenAi();
    $draft = ['campaignId' => $campaign->id, 'unitPrice' => '105.25', 'availableQuantity' => 20, 'deliveryDays' => 3, 'deliveryMode' => 'entrega', 'validUntil' => now()->addDays(3)->toDateString(), 'transportIncluded' => true, 'transportUnitPrice' => '0', 'notes' => 'Arroz branco; contacto pessoa@example.test ou +258 841234567.', 'supplierId' => $competitor->supplier_id, 'status' => 'aceite'];
    $response = $this->actingAs($supplierUser)->postJson(route('ai.proposal'), $draft)->assertOk()->assertJsonCount(2, 'findings');
    Http::assertSent(function (Request $request) use ($draft): bool {
        $state = json_decode($request['input'][1]['content'], true, 64, JSON_THROW_ON_ERROR);
        $json = json_encode($state);

        return array_keys($state['items']) === ['draft']
            && ! str_contains($json, 'SEGREDO-CONCORRENTE') && ! str_contains($json, 'supplierId')
            && ! str_contains($json, 'pessoa@example.test') && ! str_contains($json, '841234567')
            && $state['items']['draft']['unitPrice'] === '105.25'
            && $state['items']['draft']['validUntil'] === $draft['validUntil'];
    });
    expect(Proposal::count())->toBe(2);
    $this->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 100, 'delivery' => 0, 'clarity' => 0]])->assertForbidden();
    $this->actingAs($owner)->postJson(route('ai.decision', $response->json('analysisId')), ['decision' => 'reviewed'])->assertForbidden();
});

test('invalid draft and inconsistent weights are rejected before any billable request', function () {
    [$owner, $campaign] = aiComparisonFixture();
    $this->actingAs($owner)->postJson(route('ai.campaign'), [...aiCampaignDraft(), 'targetQuantity' => -1])->assertUnprocessable();
    $this->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 100, 'delivery' => 100, 'clarity' => 100]])->assertUnprocessable();
    Http::assertNothingSent();
});

test('provider failure or malformed response cannot become a recommendation or leak provider details', function (string $failure) {
    [$owner, $campaign, $cheap, $fast] = aiComparisonFixture();
    Http::fake(['https://api.openai.com/v1/responses' => match ($failure) {
        'timeout' => Http::failedConnection(),
        'authentication' => Http::response(['message' => 'INTERNAL-SECRET'], 401),
        'rate-limit' => Http::response(['message' => 'INTERNAL-SECRET'], 429),
        'malformed' => Http::response(['model' => 'gpt-6.1-terra', 'status' => 'completed', 'output' => []]),
        'out-of-range' => Http::response(juntaOpenAiResponse(['p'.$cheap->id => ['clarity' => 999, 'confidence' => 1, 'contradiction' => 0, 'alignment' => 1], 'p'.$fast->id => ['clarity' => 2, 'confidence' => 1, 'contradiction' => 0, 'alignment' => 1]])),
    }]);
    $response = $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 50, 'delivery' => 25, 'clarity' => 25]])
        ->assertStatus(503)->assertJsonMissingPath('recommendedProposalId');
    expect($response->getContent())->not->toContain('INTERNAL-SECRET');
    Http::assertSentCount(1);
    expect(AuditEntry::where('action', 'ai.review.created')->count())->toBe(0)
        ->and($campaign->fresh()->status)->toBe('em_avaliacao');
})->with(['timeout', 'authentication', 'rate-limit', 'malformed', 'out-of-range']);
