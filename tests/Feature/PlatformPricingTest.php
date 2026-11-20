<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PlatformPricingSetting;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRefund;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CollectivePurchase;
use App\Support\TestPaymentGateway;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function () {
    $this->freezeTime();
    $this->withoutVite();
    Http::preventStrayRequests();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true, 'inertia.ssr.enabled' => false]);
});

/** @return array{campaign:Campaign,organizer:User,admin:User,participants:list<Participation>} */
function pricingCampaign(TestCase $test, bool $included = false): array
{
    $organizer = User::factory()->create(['is_organizer' => true]);
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->for($organizer, 'organizer')->create(['status' => 'em_avaliacao']);
    $participants = [
        Participation::factory()->for($campaign)->create(['quantity' => 3]),
        Participation::factory()->for($campaign)->create(['quantity' => 2]),
    ];
    $supplier = Supplier::factory()->create(['verified' => true]);
    $proposal = Proposal::factory()->for($campaign)->for($supplier)->create([
        'unit_price' => '100.00', 'minimum_quantity' => 3, 'available_quantity' => 20,
        'transport_included' => $included, 'transport_unit_cents' => $included ? 0 : 200,
        'valid_until' => now()->addDays(3), 'reserved_until' => now()->addDays(2),
    ]);
    $test->actingAs($organizer)->post(route('organizador.campanhas.seleccionar', [$campaign, $proposal]))->assertSessionHasNoErrors();

    return compact('campaign', 'organizer', 'admin', 'participants');
}

/** @return array<string, mixed> */
function pricingPaymentInput(): array
{
    return ['paymentDeadline' => now()->addDay()->toIso8601String(), 'pickupPoint' => 'Ponto de recolha de Maputo'];
}

test('only administration changes the platform commission and the change is audited', function () {
    $organizer = User::factory()->create(['is_organizer' => true]);
    $this->actingAs($organizer)->get(route('admin.tarifas.index'))->assertForbidden();
    $this->put(route('admin.tarifas.update'), ['commissionPercent' => '0'])->assertForbidden();
    $this->assertDatabaseCount('platform_pricing_settings', 0);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->put(route('admin.tarifas.update'), ['commissionPercent' => '1.25'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('platform_pricing_settings', ['id' => 1, 'commission_bps' => 125, 'updated_by' => $admin->id]);
    $this->assertDatabaseHas('audit_entries', ['user_id' => $admin->id, 'action' => 'platform.pricing.updated']);
    $this->get(route('admin.tarifas.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/tarifas')->where('commissionPercent', '1.25'));
});

test('invalid platform commission values are rejected without changing the active tariff', function (string $value) {
    $admin = User::factory()->create(['is_admin' => true]);
    PlatformPricingSetting::factory()->create(['id' => 1, 'commission_bps' => 250]);

    $this->actingAs($admin)->put(route('admin.tarifas.update'), ['commissionPercent' => $value])->assertSessionHasErrors('commissionPercent');

    $this->assertDatabaseHas('platform_pricing_settings', ['id' => 1, 'commission_bps' => 250]);
    $this->assertDatabaseCount('audit_entries', 0);
})->with(['negative' => '-1', 'above 100 percent' => '100.01', 'excessive decimal precision' => '1.234']);

test('an organizer cannot waive platform income and delivery is shared by quantities without double charging transport', function () {
    $pilot = pricingCampaign($this);
    $campaign = $pilot['campaign'];
    PlatformPricingSetting::factory()->create(['id' => 1, 'commission_bps' => 125]);
    Participation::factory()->for($campaign)->create(['quantity' => 1000, 'status' => 'cancelada']);
    Participation::factory()->for($campaign)->create(['quantity' => 1000, 'user_id' => null]);
    $this->actingAs($pilot['organizer'])->put(route('admin.tarifas.delivery', $campaign), ['deliveryTotal' => '0', 'reference' => 'Tentativa'])->assertForbidden();
    $this->actingAs($pilot['admin'])->put(route('admin.tarifas.delivery', $campaign), ['deliveryTotal' => '100.00', 'reference' => 'Cotação logística 001'])->assertSessionHasNoErrors();

    $this->actingAs($pilot['organizer'])->post(route('organizador.compra.open', $campaign), [
        ...pricingPaymentInput(), 'commissionPercent' => '0', 'deliveryFee' => '0', 'deliveryZone' => 'Alteração indevida',
    ])->assertSessionHasNoErrors();

    $orders = PurchaseOrder::query()->orderBy('participation_id')->get();
    expect($orders)->toHaveCount(2);
    expect($orders->pluck('delivery_fee_cents')->all())->toBe([6000, 4000]);
    expect($orders->pluck('supplier_transport_cents')->all())->toBe([0, 0]);
    expect($orders->pluck('commission_cents')->all())->toBe([375, 250]);
    expect($orders->pluck('total_cents')->all())->toBe([36375, 24250]);
    expect($campaign->fresh()->purchase_config['delivery_zone'])->toBe('');
    expect($campaign->fresh()->purchase_config['commission_bps'])->toBe(125);
    expect($orders->sum('delivery_fee_cents'))->toBe(10000);
});

test('supplier included delivery always has zero platform delivery charge', function () {
    $pilot = pricingCampaign($this, included: true);

    $this->actingAs($pilot['organizer'])->post(route('organizador.compra.open', $pilot['campaign']), [
        ...pricingPaymentInput(), 'commissionPercent' => '0', 'deliveryFee' => '9999',
    ])->assertSessionHasNoErrors();

    expect(PurchaseOrder::sum('delivery_fee_cents'))->toBe(0);
    expect(PurchaseOrder::sum('supplier_transport_cents'))->toBe(0);
    expect(PurchaseOrder::sum('commission_cents'))->toBe(1250);
});

test('missing administrative freight quotation blocks payment opening without creating orders', function () {
    $pilot = pricingCampaign($this);

    $this->actingAs($pilot['organizer'])->post(route('organizador.compra.open', $pilot['campaign']), pricingPaymentInput())->assertSessionHasErrors('deliveryTotal');

    $this->assertDatabaseCount('purchase_orders', 0);
    expect($pilot['campaign']->fresh()->purchase_phase)->toBe('interest');
});

test('administration cannot add freight already included by the supplier', function () {
    $pilot = pricingCampaign($this, included: true);
    $before = $pilot['campaign']->fresh()->purchase_config;

    $this->actingAs($pilot['admin'])->put(route('admin.tarifas.delivery', $pilot['campaign']), ['deliveryTotal' => '100.00', 'reference' => 'Transporte duplicado'])->assertSessionHasErrors('deliveryTotal');

    expect($pilot['campaign']->fresh()->purchase_config)->toBe($before);
    expect(AuditEntry::query()->where('action', 'platform.delivery.quoted')->count())->toBe(0);
});

test('accepted purchase terms are not repriced by tariff or freight changes', function () {
    $pilot = pricingCampaign($this);
    $campaign = $pilot['campaign'];
    $this->actingAs($pilot['admin'])->put(route('admin.tarifas.delivery', $campaign), ['deliveryTotal' => '100.00', 'reference' => 'Cotação confirmada'])->assertSessionHasNoErrors();
    $this->actingAs($pilot['organizer'])->post(route('organizador.compra.open', $campaign), pricingPaymentInput())->assertSessionHasNoErrors();
    $config = $campaign->fresh()->purchase_config;
    $amounts = PurchaseOrder::query()->orderBy('id')->get()->map->getAttributes()->all();

    $this->actingAs($pilot['admin'])->put(route('admin.tarifas.update'), ['commissionPercent' => '9.00'])->assertSessionHasNoErrors();
    $this->put(route('admin.tarifas.delivery', $campaign), ['deliveryTotal' => '900.00', 'reference' => 'Tentativa de alteração'])->assertSessionHasErrors('purchase');

    expect($campaign->fresh()->purchase_config)->toBe($config);
    expect(PurchaseOrder::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($amounts);
});

test('cancellation refunds product commission and shared delivery in full', function () {
    $pilot = pricingCampaign($this);
    $campaign = $pilot['campaign'];
    $this->actingAs($pilot['admin'])->put(route('admin.tarifas.delivery', $campaign), ['deliveryTotal' => '100.00', 'reference' => 'Cotação'])->assertSessionHasNoErrors();
    $this->actingAs($pilot['organizer'])->post(route('organizador.compra.open', $campaign), pricingPaymentInput())->assertSessionHasNoErrors();
    $order = PurchaseOrder::query()->where('participation_id', $pilot['participants'][0]->id)->firstOrFail();
    $this->actingAs($order->user)->post(route('consumidor.pedidos.pay', $order), pilotPaymentData($order))->assertSessionHasNoErrors();
    $payment = $order->attempts()->firstOrFail();
    app(TestPaymentGateway::class)->simulate($payment->reference, 'paid');
    app(CollectivePurchase::class)->reconcile($payment);

    app(CollectivePurchase::class)->closeCampaign($campaign->fresh(), $pilot['admin'], true, 'Cancelamento de teste');

    expect($order->fresh()->status)->toBe('refunded');
    expect(PurchaseRefund::query()->sole()->amount_cents)->toBe($order->total_cents);
    $this->assertDatabaseHas('financial_entries', ['purchase_order_id' => $order->id, 'kind' => 'refund_paid', 'amount_cents' => $order->total_cents]);
});

test('legacy purchase snapshots keep their original supplier transport and individual delivery prices', function () {
    $campaign = Campaign::factory()->create(['purchase_config' => [
        'unit_cents' => 10000, 'commission_bps' => 275, 'transport_unit_cents' => 200,
        'delivery_zone' => 'Maputo', 'delivery_fee_cents' => 2500,
    ]]);

    $quote = app(CollectivePurchase::class)->quote($campaign, 3, 'entrega');

    expect($quote)->toBe(['merchandise_cents' => 30000, 'commission_cents' => 825, 'supplier_transport_cents' => 600, 'delivery_fee_cents' => 2500, 'total_cents' => 33925]);
});

test('the new checkout shows the shared collection point charge and cannot offer unpriced home delivery', function () {
    $pilot = pricingCampaign($this);
    $campaign = $pilot['campaign'];
    $this->actingAs($pilot['admin'])->put(route('admin.tarifas.delivery', $campaign), ['deliveryTotal' => '100.00', 'reference' => 'Cotação de teste'])->assertSessionHasNoErrors();
    $this->actingAs($pilot['organizer'])->post(route('organizador.compra.open', $campaign), pricingPaymentInput())->assertSessionHasNoErrors();
    $order = PurchaseOrder::query()->where('participation_id', $pilot['participants'][0]->id)->firstOrFail();

    $this->actingAs($order->user)->get(route('consumidor.pedidos.checkout', $order))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('purchase.config.delivery_scope', 'collection_point')->where('purchase.deliveryQuote', null)
        ->where('purchase.quote.delivery_fee_cents', 6000)->where('purchase.quote.supplier_transport_cents', 0)
        ->missing('purchase.config.delivery_shares_cents')->missing('purchase.order.conditions.delivery_shares_cents'));

    $this->post(route('consumidor.pedidos.pay', $order), [...pilotPaymentData($order), 'deliveryMethod' => 'entrega', 'deliveryAddress' => 'Morada de teste'])->assertSessionHasErrors('purchase');
    $this->assertDatabaseCount('payment_attempts', 0);
});
