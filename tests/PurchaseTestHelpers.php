<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\PlatformPricingSetting;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Tests\TestCase;

/** @return array{campaign:Campaign,organizer:User,supplier:User,consumer:User,admin:User,participation:Participation,proposal:Proposal} */
function collectivePilot(TestCase $test, int $quantity = 3, int $minimum = 3, int $available = 10, string $commission = '2.75', bool $included = false): array
{
    $organizer = User::factory()->create(['is_organizer' => true]);
    $supplier = User::factory()->create();
    $supplierModel = Supplier::factory()->create(['user_id' => $supplier->id, 'verified' => true]);
    $consumer = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $setting = PlatformPricingSetting::query()->find(1) ?? new PlatformPricingSetting;
    $setting->forceFill(['id' => 1, 'commission_bps' => Money::cents($commission)])->save();
    $campaign = Campaign::factory()->create(['organizer_id' => $organizer->id, 'status' => 'em_avaliacao', 'target_quantity' => 1000]);
    $participation = Participation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $consumer->id, 'quantity' => $quantity]);
    $proposal = Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $supplierModel->id, 'unit_price' => '100.10', 'minimum_quantity' => $minimum, 'available_quantity' => $available, 'delivery_days' => 3, 'valid_until' => now()->addDays(3), 'reserved_until' => now()->addDays(2), 'transport_included' => $included, 'transport_unit_cents' => $included ? 0 : 200]);
    $test->actingAs($organizer)->post(route('organizador.campanhas.seleccionar', [$campaign, $proposal]))->assertSessionHasNoErrors();
    if (! $included) {
        $test->actingAs($admin)->put(route('admin.tarifas.delivery', $campaign), ['deliveryTotal' => Money::decimal(200 * $quantity), 'reference' => '[TESTE] Cotação até ao ponto'])->assertSessionHasNoErrors();
    }
    $test->actingAs($organizer)->post(route('organizador.compra.open', $campaign), [
        'paymentDeadline' => now()->addDay()->toIso8601String(),
        'pickupPoint' => 'Mercado de Maputo, balcão Junta',
    ])->assertSessionHasNoErrors();

    return compact('campaign', 'organizer', 'supplier', 'consumer', 'admin', 'participation', 'proposal');
}

function pilotOrder(TestCase $test, array $pilot): PurchaseOrder
{
    $test->actingAs($pilot['consumer'])->post(route('consumidor.pedidos.store', $pilot['campaign']), [
        'deliveryMethod' => 'recolha', 'termsVersion' => config('purchases.terms_version'), 'acceptTerms' => true,
    ])->assertSessionHasNoErrors();

    return PurchaseOrder::query()->where('user_id', $pilot['consumer']->id)->where('campaign_id', $pilot['campaign']->id)->firstOrFail();
}

function pilotPayment(TestCase $test, array $pilot, PurchaseOrder $order): PaymentAttempt
{
    $test->actingAs($pilot['consumer'])->post(route('consumidor.pedidos.pay', $order), pilotPaymentData($order))->assertSessionHasNoErrors();

    return PaymentAttempt::query()->where('purchase_order_id', $order->id)->latest('id')->firstOrFail();
}

/** @return array<string,mixed> */
function pilotPaymentData(PurchaseOrder $order): array
{
    return ['deliveryMethod' => $order->delivery_method, 'deliveryAddress' => $order->delivery_address,
        'termsVersion' => $order->terms_version, 'acceptTerms' => true,
        'paymentMethod' => 'mpesa', 'paymentPhone' => '+258840000000'];
}
