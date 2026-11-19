<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../OpenAiTestHelpers.php';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.enabled' => true, 'services.openai.key' => 'history-test-key', 'services.openai.model' => 'gpt-6.1-terra']);
});

/** @return array{User,Campaign,Proposal,Proposal} */
function supplierHistoryFixture(): array
{
    $owner = User::factory()->create(['is_organizer' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $owner->id, 'status' => 'em_avaliacao', 'target_quantity' => 10]);
    Participation::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 10]);
    $offers = [];
    foreach (range(1, 2) as $index) {
        $offers[] = Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => Supplier::factory()->create(['verified' => true])->id,
            'unit_price' => '100.01', 'available_quantity' => 20, 'minimum_quantity' => 5, 'reserved_until' => now()->addDays(4),
            'valid_until' => now()->addDays(5), 'delivery_days' => 3, 'delivery_mode' => 'recolha', 'transport_included' => false, 'transport_unit_cents' => 250]);
    }

    return [$owner, $campaign, $offers[0], $offers[1]];
}

test('comparison weights recorded supplier history and itemizes exact product and transport totals', function () {
    $this->freezeTime();
    [$owner, $campaign, $experienced, $new] = supplierHistoryFixture();
    SupplyOrder::factory()->count(2)->create(['supplier_id' => $experienced->supplier_id, 'quantity' => 10, 'received_quantity' => 10, 'status' => 'received']);
    fakeJuntaOpenAi();

    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 0, 'delivery' => 0, 'clarity' => 0, 'history' => 100]])
        ->assertOk()->assertJsonPath('recommendedProposalId', $experienced->id)->assertJsonPath('rows.0.historyScore', 75)
        ->assertJsonPath('rows.0.productTotalCents', 100010)->assertJsonPath('rows.0.transportTotalCents', 2500)->assertJsonPath('rows.0.totalCents', 102510)
        ->assertJsonPath('rows.0.supplierHistory.fullyReceived', 2)->assertJsonPath('rows.0.supplierHistory.onTimeRate', null)
        ->assertJsonPath('rows.1.historyScore', 50)->assertJsonPath('rows.1.eligible', true);

    Http::assertSent(function (Request $request) use ($experienced): bool {
        $state = json_decode($request['input'][1]['content'], true, 64, JSON_THROW_ON_ERROR);

        return $state['supplierHistory']['p'.$experienced->id]['fullyReceived'] === 2
            && ! str_contains($request->body(), 'company_name') && ! str_contains($request->body(), 'contact_name');
    });
    $this->assertDatabaseHas('proposals', ['id' => $experienced->id, 'status' => 'submetida']);
    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'status' => 'em_avaliacao']);
});

test('open supply orders are not failures and new suppliers keep a neutral history score', function () {
    [$owner, $campaign, $pending, $new] = supplierHistoryFixture();
    SupplyOrder::factory()->create(['supplier_id' => $pending->supplier_id, 'quantity' => 10, 'received_quantity' => 4, 'status' => 'partial']);
    SupplyOrder::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $pending->supplier_id, 'quantity' => 10, 'received_quantity' => 10]);
    fakeJuntaOpenAi();

    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 0, 'delivery' => 0, 'clarity' => 0, 'history' => 100]])
        ->assertOk()->assertJsonPath('recommendedProposalId', null)->assertJsonPath('rows.0.historyScore', 50)->assertJsonPath('rows.1.historyScore', 50)
        ->assertJsonPath('rows.0.supplierHistory.orders', 1)->assertJsonPath('rows.0.supplierHistory.inProgress', 1)
        ->assertJsonPath('rows.0.supplierHistory.partial', 1)->assertJsonPath('rows.0.eligible', true);

    Http::assertSentCount(1);
});

test('invalid history weights return 422 before contacting the provider', function (int $history) {
    [$owner, $campaign] = supplierHistoryFixture();

    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 50, 'delivery' => 25, 'clarity' => 25, 'history' => $history]])
        ->assertUnprocessable();

    Http::assertNothingSent();
})->with([-1, 1, 101]);

test('finished partial receipts remain visible without inventing supplier punctuality', function () {
    [$owner, $campaign, $partial, $new] = supplierHistoryFixture();
    $past = Campaign::factory()->create(['purchase_phase' => 'completed', 'status' => 'concluida']);
    SupplyOrder::factory()->create(['campaign_id' => $past->id, 'supplier_id' => $partial->supplier_id, 'quantity' => 10, 'received_quantity' => 4, 'status' => 'partial']);
    fakeJuntaOpenAi();

    $this->actingAs($owner)->postJson(route('ai.compare', $campaign), ['weights' => ['cost' => 0, 'delivery' => 0, 'clarity' => 0, 'history' => 100]])
        ->assertOk()->assertJsonPath('rows.0.proposalId', $new->id)->assertJsonPath('rows.1.supplierHistory.finished', 1)
        ->assertJsonPath('rows.1.historyScore', 33.3)->assertJsonPath('rows.1.supplierHistory.partial', 1)->assertJsonPath('rows.1.supplierHistory.onTimeRate', null);

    Http::assertSentCount(1);
});
