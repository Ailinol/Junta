<?php

use App\Models\FinancialEntry;
use App\Models\PaymentAttempt;
use App\Models\PurchaseRefund;
use App\Models\TestGatewayTransaction;
use App\Support\CollectivePurchase;
use App\Support\PaymentGateway;
use App\Support\TestPaymentGateway;

beforeEach(function () {
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->freezeSecond();
});

test('a provider timeout after accepting a charge preserves its reference for reconciliation without charging again', function () {
    $pilot = collectivePilot($this);
    $order = pilotOrder($this, $pilot);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('available')->andReturn(true);
    $gateway->shouldReceive('availableMethods')->andReturn(app(TestPaymentGateway::class)->availableMethods());
    $reference = null;
    $gateway->shouldReceive('initiate')->once()->andReturnUsing(function (string $id) use (&$reference): void {
        $reference = $id;
        throw new RuntimeException('Simulated provider timeout after acceptance');
    });
    $gateway->shouldReceive('lookup')->andReturnUsing(fn (string $id): array => [
        'reference' => $id, 'status' => 'paid', 'amount_cents' => $order->total_cents,
        'currency' => 'MZN', 'settled_at' => now()->toIso8601String(),
    ]);
    $this->instance(PaymentGateway::class, $gateway);

    $this->actingAs($pilot['consumer'])->post(route('consumidor.pedidos.pay', $order), pilotPaymentData($order))->assertSessionHasErrors('payment');
    $this->assertDatabaseHas('payment_attempts', ['reference' => $reference, 'status' => 'pending', 'holds_stock' => true]);
    expect($pilot['campaign']->fresh()->held_quantity)->toBe(3);
    $this->post(route('consumidor.pedidos.reconcile', $order))->assertSessionHasNoErrors();
    $this->post(route('consumidor.pedidos.reconcile', $order))->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe('paid');
    expect(PaymentAttempt::count())->toBe(1);
    expect(FinancialEntry::where('kind', 'payment_received')->count())->toBe(1);
});

test('a committed attempt missing at the provider is resumed with the same reference', function () {
    $pilot = collectivePilot($this);
    $order = pilotOrder($this, $pilot);
    $attempt = pilotPayment($this, $pilot, $order);
    TestGatewayTransaction::where('reference', $attempt->reference)->delete();

    app(CollectivePurchase::class)->reconcile($attempt);
    $this->assertDatabaseHas('test_gateway_transactions', ['reference' => $attempt->reference, 'status' => 'pending', 'amount_cents' => $order->total_cents]);
    $this->assertDatabaseCount('payment_attempts', 1);
    expect($pilot['campaign']->fresh()->held_quantity)->toBe(3);
});

test('repeated successful payment reconciliation uses one internal notification and one financial event', function () {
    $pilot = collectivePilot($this);
    $order = pilotOrder($this, $pilot);
    $attempt = pilotPayment($this, $pilot, $order);
    app(TestPaymentGateway::class)->simulate($attempt->reference, 'paid');
    app(CollectivePurchase::class)->reconcile($attempt);
    app(CollectivePurchase::class)->reconcile($attempt);
    expect($pilot['consumer']->notifications()->where('data->eventKey', 'payment.confirmed:'.$attempt->reference)->count())->toBe(1);
    expect(FinancialEntry::where('kind', 'payment_received')->count())->toBe(1);
});

test('reference mismatch never confirms payment and preserves capacity while under review', function () {
    $pilot = collectivePilot($this);
    $order = pilotOrder($this, $pilot);
    $attempt = pilotPayment($this, $pilot, $order);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('lookup')->once()->with($attempt->reference)->andReturn([
        'reference' => 'a-different-reference', 'status' => 'paid', 'amount_cents' => $attempt->amount_cents,
        'currency' => 'MZN', 'settled_at' => now()->toIso8601String(),
    ]);
    $this->instance(PaymentGateway::class, $gateway);
    app(CollectivePurchase::class)->reconcile($attempt);
    expect($attempt->fresh()->status)->toBe('review');
    expect($pilot['campaign']->fresh()->held_quantity)->toBe(3);
    expect($order->fresh()->status)->toBe('payment_pending');
    expect(FinancialEntry::where('kind', 'payment_received')->count())->toBe(0);
});

test('an uncertain refund is resumed by its original reference without a second external refund', function () {
    $pilot = collectivePilot($this, minimum: 5);
    $order = pilotOrder($this, $pilot);
    $attempt = pilotPayment($this, $pilot, $order);
    $real = app(TestPaymentGateway::class);
    $real->simulate($attempt->reference, 'paid');
    app(CollectivePurchase::class)->reconcile($attempt);
    $this->travel(25)->hours();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('lookup')->andReturnUsing(fn (string $reference): ?array => $real->lookup($reference));
    $refundReference = null;
    $calls = 0;
    $externalRefunds = 0;
    $gateway->shouldReceive('refund')->twice()->andReturnUsing(function (string $payment, string $reference) use (&$refundReference, &$calls, &$externalRefunds): bool {
        $calls++;
        if ($refundReference === null) {
            $refundReference = $reference;
            $externalRefunds++;
            throw new RuntimeException('Refund accepted with response lost');
        }
        expect($reference)->toBe($refundReference);

        return true;
    });
    $this->instance(PaymentGateway::class, $gateway);
    $purchases = app(CollectivePurchase::class);
    $purchases->closeCampaign($pilot['campaign'], $pilot['organizer']);
    expect($order->fresh()->status)->toBe('refund_pending');
    $purchases->processRefunds();
    expect($order->fresh()->status)->toBe('refunded');
    expect(PurchaseRefund::count())->toBe(1);
    expect(FinancialEntry::where('kind', 'refund_paid')->count())->toBe(1);
    expect($calls)->toBe(2)->and($externalRefunds)->toBe(1);
});
