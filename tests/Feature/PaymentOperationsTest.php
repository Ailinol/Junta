<?php

use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('commercial clock inputs use Maputo UTC persistence and reject payment at the exact deadline', function (string $suffix) {
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-11-11T07:00:00Z'));
    $supplierUser = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id, 'verified' => true]);
    $campaign = Campaign::factory()->create(['status' => 'em_avaliacao']);
    $consumer = User::factory()->create();
    Participation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $consumer->id, 'quantity' => 3]);
    $this->actingAs($supplierUser)->post(route('fornecedor.propostas.store'), [
        'campaignId' => $campaign->id, 'deliveryMode' => 'recolha',
        'unitPrice' => '100.10', 'minimumQuantity' => 3, 'availableQuantity' => 10, 'deliveryDays' => 3,
        'validUntil' => '2026-11-12T10:00'.$suffix, 'reservedUntil' => '2026-11-12T10:00'.$suffix,
        'transportIncluded' => true, 'transportUnitPrice' => '0',
    ])->assertSessionHasNoErrors();
    $offer = Proposal::query()->where('campaign_id', $campaign->id)->sole();
    $this->actingAs($campaign->organizer)->post(route('organizador.campanhas.seleccionar', [$campaign, $offer]))->assertSessionHasNoErrors();
    expect($offer->fresh()->valid_until->utc()->format('Y-m-d H:i:s'))->toBe('2026-11-12 08:00:00');
    $this->actingAs($campaign->organizer)->post(route('organizador.compra.open', $campaign), [
        'commissionPercent' => '2.50', 'paymentDeadline' => '2026-11-12T09:00'.$suffix,
        'pickupPoint' => 'Maputo', 'deliveryZone' => '', 'deliveryFee' => '0',
    ])->assertSessionHasNoErrors();
    expect($campaign->fresh()->payment_deadline->utc()->format('Y-m-d H:i:s'))->toBe('2026-11-12 07:00:00');
    $order = pilotOrder($this, ['consumer' => $consumer, 'campaign' => $campaign]);
    $this->travelTo(CarbonImmutable::parse('2026-11-12T07:00:00Z'));
    $this->post(route('consumidor.pedidos.pay', $order), pilotPaymentData($order))->assertSessionHasErrors('purchase');
    $this->assertDatabaseCount('payment_attempts', 0);
})->with(['', '+02:00']);

test('interest ends exactly at its deadline for manual entry and scheduled processing', function () {
    $this->freezeSecond();
    $campaign = Campaign::factory()->create(['deadline' => now()]);
    $this->actingAs(User::factory()->create())->post(route('consumidor.campanhas.aderir', $campaign), ['quantity' => 2])->assertSessionHasErrors('quantity');
    $this->artisan('junta:process-platform')->assertSuccessful();
    expect($campaign->fresh()->status)->toBe('em_avaliacao');
    $this->assertDatabaseCount('participations', 0);
});

test('date only deadlines retain the historical UTC convention and malformed local dates are rejected', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11T07:00:00Z'));
    $owner = User::factory()->create(['is_organizer' => true]);
    $input = ['title' => 'Compra de arroz', 'description' => 'Arroz branco em sacos selados.', 'category' => 'alimentacao', 'status' => 'ativa', 'unit' => 'saco', 'targetQuantity' => 10, 'deadline' => '2026-11-13', 'location' => 'Maputo', 'deliveryMode' => 'recolha'];
    $this->actingAs($owner)->post(route('organizador.campanhas.store'), $input)->assertSessionHasNoErrors();
    expect(Campaign::firstOrFail()->getRawOriginal('deadline'))->toBe('2026-11-13 00:00:00');
    $this->post(route('organizador.campanhas.store'), [...$input, 'deadline' => '2027-02-31T12:00'])->assertSessionHasErrors('deadline');
    $this->assertDatabaseCount('campaigns', 1);
});

test('finance labels global and filtered totals and exports exactly the same filtered financial events', function () {
    $this->withoutVite();
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    $included = FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'payment_received', 'amount_cents' => 10000, 'reference' => 'MATCH-INCLUDED', 'reason' => 'needle', 'created_at' => '2026-11-10 22:30:00']);
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'payment_received', 'amount_cents' => 30000, 'reference' => 'OUTSIDE-LOCAL-DAY', 'reason' => 'needle', 'created_at' => '2026-11-11 22:30:00']);
    FinancialEntry::factory()->create(['campaign_id' => $other->id, 'kind' => 'refund_paid', 'amount_cents' => 2500, 'reference' => 'OTHER-CAMPAIGN', 'reason' => 'needle', 'created_at' => '2026-11-10 22:30:00']);
    $filters = ['campaignId' => $campaign->id, 'search' => 'needle', 'from' => '2026-11-11', 'to' => '2026-11-11'];
    $this->actingAs($admin)->get(route('admin.compras.index', $filters))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totalsScope', 'global')->where('filteredTotalsScope', 'current_filters')
        ->where('totals.payment_received', 40000)->where('totals.refund_paid', 2500)->where('totals.net_received', 37500)
        ->where('filteredTotals.payment_received', 10000)->where('filteredTotals.net_received', 10000)
        ->where('events.total', 1)->where('events.data.0.id', $included->id));
    $csv = $this->get(route('admin.compras.export', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain('MATCH-INCLUDED')->not->toContain('OUTSIDE-LOCAL-DAY')->not->toContain('OTHER-CAMPAIGN');
});
