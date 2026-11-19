<?php

use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true, 'inertia.ssr.enabled' => false]);
});

/** @return array<string,mixed> */
function commitmentInput(Campaign $campaign): array
{
    return ['campaignId' => $campaign->id, 'unitPrice' => '50.50', 'availableQuantity' => 30, 'minimumQuantity' => 10,
        'deliveryDays' => 3, 'deliveryMode' => 'recolha', 'validUntil' => now()->addDays(5)->toIso8601String(),
        'reservedUntil' => now()->addDays(4)->toIso8601String(), 'transportIncluded' => false, 'transportUnitPrice' => '1.25'];
}

/** @return array{Campaign,Supplier,Proposal} */
function selectedCommitment(TestCase $test): array
{
    $supplier = Supplier::factory()->create(['verified' => true]);
    $campaign = Campaign::factory()->create(['status' => 'em_avaliacao']);
    $test->actingAs($supplier->user)->post(route('fornecedor.propostas.store'), commitmentInput($campaign))->assertSessionHasNoErrors();
    $proposal = Proposal::query()->where('campaign_id', $campaign->id)->sole();
    $test->actingAs($campaign->organizer)->post(route('organizador.campanhas.seleccionar', [$campaign, $proposal]))->assertSessionHasNoErrors();

    return [$campaign->fresh(), $supplier, $proposal->fresh()];
}

test('submitting a supplier proposal requires each commercial commitment field', function (string $field) {
    $supplier = Supplier::factory()->create(['verified' => true]);
    $campaign = Campaign::factory()->create();
    $input = commitmentInput($campaign);
    unset($input[$field]);
    $this->actingAs($supplier->user)->post(route('fornecedor.propostas.store'), $input)->assertSessionHasErrors($field);
    $this->assertDatabaseCount('proposals', 0);
})->with(['unitPrice', 'availableQuantity', 'minimumQuantity', 'deliveryDays', 'validUntil', 'reservedUntil', 'transportIncluded', 'transportUnitPrice']);

test('submission rejects inconsistent minimum transport and expired commercial dates', function (array $changes, string $field) {
    $supplier = Supplier::factory()->create(['verified' => true]);
    $campaign = Campaign::factory()->create();
    $this->actingAs($supplier->user)->post(route('fornecedor.propostas.store'), [...commitmentInput($campaign), ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('proposals', 0);
})->with([[['minimumQuantity' => 31], 'minimumQuantity'], [['transportIncluded' => true, 'transportUnitPrice' => '1.25'], 'transportUnitPrice'], [['validUntil' => '2000-01-01'], 'validUntil'], [['reservedUntil' => '2000-01-01'], 'reservedUntil']]);

test('included transport commits zero additional cost and an unverified supplier cannot submit', function () {
    $supplier = Supplier::factory()->create(['verified' => true]);
    $campaign = Campaign::factory()->create();
    $input = [...commitmentInput($campaign), 'transportIncluded' => true];
    unset($input['transportUnitPrice']);
    $this->actingAs($supplier->user)->post(route('fornecedor.propostas.store'), $input)->assertSessionHasNoErrors();
    expect(Proposal::query()->sole()->transport_unit_cents)->toBe(0);
    $other = Supplier::factory()->create(['verified' => false]);
    $this->actingAs($other->user)->post(route('fornecedor.propostas.store'), commitmentInput($campaign))->assertSessionHasErrors('supplierId');
    $this->assertDatabaseCount('proposals', 1);
});

test('selection freezes the submitted commercial conditions and sends an informational notification', function () {
    [$campaign, $supplier, $proposal] = selectedCommitment($this);
    expect($proposal->supplier_confirmed_at)->toBeNull();
    expect($campaign->purchase_config)->toMatchArray(['proposal_id' => $proposal->id, 'unit_cents' => 5050, 'minimum_quantity' => 10, 'available_quantity' => 30, 'transport_unit_cents' => 125]);
    expect($campaign->hasFixedPurchaseTerms())->toBeFalse();
    expect($campaign->purchase_config)->not->toHaveKey('commission_bps');
    $notification = $supplier->user->notifications()->sole()->data;
    expect($notification['message'])->toBe('A sua proposta para '.$campaign->title.' foi seleccionada.');
    expect($notification['link'])->toBe(route('fornecedor.propostas.show', $proposal, absolute: false));
    $this->assertDatabaseCount('purchase_orders', 0);
    $this->assertDatabaseCount('financial_entries', 0);
    $this->actingAs($supplier->user)->get(route('fornecedor.propostas.edit', $proposal))->assertRedirect(route('fornecedor.propostas.show', $proposal));
    $this->patch(route('fornecedor.propostas.update', $proposal), [...commitmentInput($campaign), 'unitPrice' => '1.00'])->assertSessionHasErrors();
    $this->post(route('fornecedor.propostas.withdraw', $proposal), ['reason' => 'Sem disponibilidade'])->assertSessionHasErrors();
    expect($proposal->fresh()->unit_price)->toBe('50.50');
});

test('opening payments needs no supplier action and uses the selected snapshot', function () {
    [$campaign, $supplier, $proposal] = selectedCommitment($this);
    $consumer = User::factory()->create();
    Participation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $consumer->id, 'quantity' => 10]);
    $this->actingAs($consumer)->get(route('consumidor.acompanhamento.show', ['id' => $campaign->id]))->assertInertia(fn (Assert $page) => $page
        ->where('purchase.quote', null)->where('purchase.progress.canPay', false));
    Proposal::query()->whereKey($proposal->id)->update(['unit_price' => '99.99']);
    $this->actingAs($campaign->organizer)->post(route('organizador.compra.open', $campaign), ['commissionPercent' => '2.50', 'paymentDeadline' => now()->addDay()->toIso8601String(), 'pickupPoint' => 'Maputo', 'deliveryZone' => '', 'deliveryFee' => '0'])->assertSessionHasNoErrors();
    expect($campaign->fresh()->purchase_phase)->toBe('payment');
    expect($campaign->fresh()->purchase_config['unit_cents'])->toBe(5050);
    expect($campaign->fresh()->hasFixedPurchaseTerms())->toBeTrue();
    expect($proposal->fresh()->supplier_confirmed_at)->toBeNull();
    $this->assertDatabaseCount('purchase_orders', 1);
    expect(PurchaseOrder::query()->sole()->merchandise_cents)->toBe(50500);
    $this->assertDatabaseCount('financial_entries', 0);
});

test('a payment deadline outside either committed validity window is rejected clearly', function (bool $shortValidity) {
    [$campaign] = selectedCommitment($this);
    $conditions = $campaign->purchase_config;
    $conditions[$shortValidity ? 'price_valid_until' : 'reserved_until'] = now()->addHour()->toIso8601String();
    $campaign->purchase_config = $conditions;
    $campaign->save();
    $this->actingAs($campaign->organizer)->post(route('organizador.compra.open', $campaign), ['commissionPercent' => '0', 'paymentDeadline' => now()->addDay()->toIso8601String(), 'pickupPoint' => 'Maputo', 'deliveryZone' => '', 'deliveryFee' => '0'])
        ->assertSessionHasErrors(['paymentDeadline' => 'O prazo de pagamento tem de terminar dentro da validade da proposta e da reserva.']);
    expect($campaign->fresh()->purchase_phase)->toBe('interest');
    $this->assertDatabaseCount('purchase_orders', 0);
})->with([true, false]);

test('selected suppliers report a problem without changing any campaign or financial state', function () {
    [$campaign, $supplier, $proposal] = selectedCommitment($this);
    $consumer = User::factory()->create();
    Participation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $consumer->id, 'quantity' => 10]);
    $this->actingAs($campaign->organizer)->post(route('organizador.compra.open', $campaign), ['commissionPercent' => '0', 'paymentDeadline' => now()->addDay()->toIso8601String(), 'pickupPoint' => 'Maputo', 'deliveryZone' => '', 'deliveryFee' => '0'])->assertSessionHasNoErrors();
    $campaign->refresh();
    $entry = FinancialEntry::factory()->create(['campaign_id' => $campaign->id]);
    $financialBefore = $entry->refresh()->getAttributes();
    $ordersBefore = PurchaseOrder::query()->where('campaign_id', $campaign->id)->get()->toArray();
    $before = $campaign->getAttributes();
    $this->travel(30)->seconds();
    $this->actingAs($supplier->user)->from(route('fornecedor.propostas.show', $proposal))->post(route('fornecedor.propostas.problem', $proposal), ['reason' => 'stock', 'description' => 'O lote ficou indisponível.'])->assertSessionHasNoErrors();
    expect($campaign->fresh()->getAttributes())->toBe($before);
    expect($proposal->fresh()->status)->toBe('aceite');
    expect($campaign->organizer->notifications()->where('data->title', 'Problema com a proposta seleccionada')->sole()->data['message'])->toContain('O lote ficou indisponível.');
    $this->assertDatabaseHas('audit_entries', ['action' => 'proposal.problem_reported', 'subject_id' => $proposal->id]);
    expect($entry->fresh()->getAttributes())->toBe($financialBefore);
    expect(PurchaseOrder::query()->where('campaign_id', $campaign->id)->get()->toArray())->toBe($ordersBefore);
    $this->assertDatabaseCount('financial_entries', 1);
    $this->assertDatabaseCount('purchase_orders', 1);
    $this->assertDatabaseCount('payment_attempts', 0);
    $this->assertDatabaseCount('purchase_refunds', 0);
});

test('the old confirmation URL permanently redirects to the owned detail and has no POST route', function () {
    [$campaign, $supplier, $proposal] = selectedCommitment($this);
    $this->actingAs($supplier->user)->get(route('fornecedor.compra', $campaign))->assertStatus(301)->assertRedirect(route('fornecedor.propostas.show', $proposal));
    $this->post('/fornecedor/campanhas/'.$campaign->id.'/confirmacao', [])->assertStatus(405);
    $other = Supplier::factory()->create(['verified' => true]);
    $this->actingAs($other->user)->get(route('fornecedor.compra', $campaign))->assertNotFound();
    $this->get(route('fornecedor.propostas.show', $proposal))->assertNotFound();
    $this->post(route('fornecedor.propostas.problem', $proposal), ['reason' => 'stock', 'description' => 'Outro fornecedor.'])->assertNotFound();
});

test('supplier details expose only their own proposal and supply totals even for a supplier who organizes campaigns', function () {
    [$campaign, $supplier, $proposal] = selectedCommitment($this);
    $supplier->user->forceFill(['is_organizer' => true])->save();
    $other = Proposal::factory()->create(['campaign_id' => $campaign->id, 'status' => 'recusada']);
    $ownCampaign = Campaign::factory()->create(['organizer_id' => $supplier->user_id]);
    Proposal::factory()->create(['campaign_id' => $ownCampaign->id]);
    SupplyOrder::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 20, 'received_quantity' => 10]);
    $this->actingAs($supplier->user)->get(route('fornecedor.propostas.show', $proposal))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('fornecedor/propostas/detalhes')->has('business.proposals', 1)->where('business.proposals.0.id', $proposal->id)
        ->has('business.participations', 0)->where('supply.quantity', 20)->where('supply.received_quantity', 10)->missing('orders'));
    $this->get(route('fornecedor.propostas.show', $other))->assertNotFound();
});

test('legacy selected proposals are usable only with complete commercial conditions and are not backfilled by reads', function (bool $complete) {
    $campaign = Campaign::factory()->create(['status' => 'proposta_selecionada']);
    Proposal::factory()->create(['campaign_id' => $campaign->id, 'status' => 'aceite', 'minimum_quantity' => $complete ? 2 : null, 'supplier_confirmed_at' => null]);
    $this->actingAs($campaign->organizer)->get(route('organizador.compra.show', $campaign))->assertInertia(fn (Assert $page) => $page
        ->where('operations.canOpenPayments', $complete)
        ->where($complete ? 'organizerCampaign.state.primaryAction.code' : 'organizerCampaign.state.primaryAction', $complete ? 'open_payments' : null));
    expect($campaign->fresh()->purchase_config)->toBeNull();
})->with([true, false]);

test('a commercial snapshot does not remove the existing right to cancel before payments', function () {
    [$campaign] = selectedCommitment($this);
    $this->actingAs($campaign->organizer)->post(route('organizador.campanhas.cancel', $campaign), ['reason' => 'O grupo decidiu não avançar.'])->assertSessionHasNoErrors();
    expect($campaign->fresh()->status)->toBe('cancelada');
    $this->assertDatabaseCount('purchase_orders', 0);
    $this->assertDatabaseCount('financial_entries', 0);
});
