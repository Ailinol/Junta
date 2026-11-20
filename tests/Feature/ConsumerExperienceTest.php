<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseUpdated;
use App\Support\CollectivePurchase;
use App\Support\TestPaymentGateway;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true, 'inertia.ssr.enabled' => false]);
});

test('checkout exposes wallet methods owner defaults and integer totals without initiating payment', function () {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $order = PurchaseOrder::where('participation_id', $pilot['participation']->id)->sole();
    $legacy = $pilot['campaign']->fresh()->purchase_config;
    unset($legacy['pricing_policy'], $legacy['delivery_scope'], $legacy['delivery_provider'], $legacy['delivery_total_cents'], $legacy['delivery_shares_cents'], $legacy['platform_delivery_quote']);
    $legacy['delivery_zone'] = 'Bairro Central';
    $legacy['delivery_fee_cents'] = 2500;
    $pilot['campaign']->forceFill(['purchase_config' => $legacy])->save();
    $order->forceFill(['conditions' => $legacy, 'supplier_transport_cents' => 600, 'delivery_fee_cents' => 0])->save();
    $pilot['consumer']->forceFill(['contact_preferences' => ['phone' => '+258841231234', 'address' => 'Rua da Cooperação 24']])->save();

    $this->actingAs($pilot['consumer'])->get(route('consumidor.pedidos.checkout', $order))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('consumidor/checkout')->where('purchase.view.label', 'A pagar')->where('purchase.view.primaryAction.label', 'Pagar')
        ->where('purchase.paymentMethods.0.code', 'mpesa')->where('purchase.paymentMethods.1.code', 'emola')->where('purchase.paymentMethods.2.code', 'mkesh')
        ->has('purchase.paymentMethods', 3)->where('purchase.paymentContact', '+258841231234')->where('purchase.deliveryAddress', 'Rua da Cooperação 24')
        ->where('purchase.quote.total_cents', 31456)->where('purchase.view.deadline.isPast', false)->where('purchase.order.accepted_at', null));
    $this->get(route('consumidor.pedidos.checkout', ['order' => $order->id, 'delivery' => 'entrega']))->assertInertia(fn (Assert $page) => $page
        ->where('purchase.preferredDelivery', 'entrega')->where('purchase.deliveryQuote.total_cents', 33956));
    expect($order->fresh()->delivery_method)->toBe('recolha');
    $this->assertDatabaseCount('payment_attempts', 0);
    $this->assertDatabaseCount('financial_entries', 0);
    expect($pilot['campaign']->fresh()->held_quantity)->toBe(0);
});

test('checkout protects ownership and resumes the intended payment destination after login', function () {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $order = PurchaseOrder::where('participation_id', $pilot['participation']->id)->sole();
    $this->actingAs(User::factory()->create())->get(route('consumidor.pedidos.checkout', $order))->assertNotFound();
    auth()->logout();
    $destination = route('consumidor.pedidos.checkout', $order);
    $this->get($destination)->assertRedirect(route('login'))->assertSessionHas('url.intended', $destination);
    $this->post(route('login.store'), ['email' => $pilot['consumer']->email, 'password' => 'password'])->assertRedirect($destination);
});

test('checkout rejects unavailable methods malformed phones and missing terms before charging', function (string $field, mixed $value, string $error) {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $order = PurchaseOrder::where('participation_id', $pilot['participation']->id)->sole();
    $data = pilotPaymentData($order);
    $data[$field] = $value;

    $this->actingAs($pilot['consumer'])->post(route('consumidor.pedidos.pay', $order), $data)->assertSessionHasErrors($error);
    $this->assertDatabaseCount('payment_attempts', 0);
    $this->assertDatabaseCount('test_gateway_transactions', 0);
})->with([['paymentMethod', 'unknown', 'paymentMethod'], ['paymentPhone', '841234567', 'paymentPhone'], ['paymentPhone', '+25884000000', 'paymentPhone'], ['acceptTerms', false, 'acceptTerms']]);

test('a pending confirmation hides the full phone and cannot initiate a second charge', function () {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $order = PurchaseOrder::where('participation_id', $pilot['participation']->id)->sole();
    $this->actingAs($pilot['consumer'])->post(route('consumidor.pedidos.pay', $order), pilotPaymentData($order))->assertSessionHasNoErrors();
    $this->get(route('consumidor.pedidos.checkout', $order))->assertInertia(fn (Assert $page) => $page
        ->where('purchase.view.code', 'payment_pending')->where('purchase.view.primaryAction', null)
        ->where('purchase.confirmation.phone', '+258 84 *** 0000')->where('purchase.confirmation.canRetry', false)
        ->missing('purchase.order.attempts.0.payment_phone'));
    $this->post(route('consumidor.pedidos.pay', $order), [...pilotPaymentData($order), 'paymentMethod' => 'emola'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('payment_attempts', 1);
    $this->assertDatabaseCount('test_gateway_transactions', 1);
    expect($pilot['campaign']->fresh()->held_quantity)->toBe(3);
    expect(PaymentAttempt::sole()->payment_method)->toBe('mpesa');
});

test('partial reloads expose payment results and only allow retries after confirmed failure', function (string $result, string $state, bool $retry) {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $order = PurchaseOrder::where('participation_id', $pilot['participation']->id)->sole();
    $this->actingAs($pilot['consumer'])->post(route('consumidor.pedidos.pay', $order), pilotPaymentData($order));
    $this->post(route('consumidor.pedidos.simulate', $order), ['status' => $result])->assertSessionHasNoErrors();
    $this->get(route('consumidor.pedidos.checkout', $order))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('purchase', fn (Assert $reload) => $reload->where('purchase.view.code', $state)->where('purchase.confirmation.canRetry', $retry)->missing('business')));
})->with([['paid', 'paid', false], ['failed', 'payment', true]]);

test('refund tracking is final with no action deadline or minimum timeline', function () {
    $this->freezeSecond();
    $pilot = collectivePilot($this, minimum: 5);
    $order = pilotOrder($this, $pilot);
    $attempt = pilotPayment($this, $pilot, $order);
    app(TestPaymentGateway::class)->simulate($attempt->reference, 'paid');
    app(CollectivePurchase::class)->reconcile($attempt);
    $this->travel(25)->hours();
    app(CollectivePurchase::class)->closeCampaign($pilot['campaign'], $pilot['organizer']);
    app(CollectivePurchase::class)->processRefunds();

    $this->get(route('consumidor.acompanhamento.show', $pilot['campaign']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('purchase.view.label', 'Reembolsado')->where('purchase.view.isFinal', true)->where('purchase.view.primaryAction', null)
        ->where('purchase.view.deadline', null)->has('purchase.view.steps', 0)->where('purchase.order.refunds.0.amount_cents', 31456));
});

test('consumer list puts payable purchases first and never exposes a collection code', function () {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $other = Campaign::factory()->create(['status' => 'concluida', 'purchase_phase' => 'completed']);
    Participation::factory()->create(['campaign_id' => $other->id, 'user_id' => $pilot['consumer']->id]);

    $this->actingAs($pilot['consumer'])->get(route('consumidor.campanhas.index'))->assertInertia(fn (Assert $page) => $page
        ->has('consumerOrders', 2)->where('consumerOrders.0.campaign.id', $pilot['campaign']->id)->where('consumerOrders.0.state.primaryAction.code', 'pay')
        ->missing('consumerOrders.0.collectionCode')->where('consumerOrders.1.state.isFinal', true));
});

test('only the owner of a paid ready order receives the private collection code', function () {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $order = pilotOrder($this, $pilot);
    $attempt = pilotPayment($this, $pilot, $order);
    app(TestPaymentGateway::class)->simulate($attempt->reference, 'paid');
    $purchases = app(CollectivePurchase::class);
    $purchases->reconcile($attempt);
    $this->travel(25)->hours();
    $purchases->closeCampaign($pilot['campaign'], $pilot['organizer']);
    $purchases->receiveSupply($pilot['organizer'], $pilot['campaign'], ['receivedQuantity' => 3, 'reference' => 'receipt', 'reason' => 'Goods received']);
    $purchases->prepareOrder($pilot['organizer'], $order);

    $this->get(route('consumidor.acompanhamento.show', $pilot['campaign']))->assertInertia(fn (Assert $page) => $page
        ->where('purchase.view.label', 'Pronto para levantar')->where('purchase.order.collectionCode', fn (string $code): bool => strlen($code) === 12));
    $this->get(route('consumidor.pedidos.checkout', $order))->assertRedirect(route('consumidor.acompanhamento.show', $pilot['campaign']));
    $this->actingAs($pilot['organizer'])->get(route('consumidor.pedidos.checkout', $order))->assertNotFound();
    $this->get(route('consumidor.acompanhamento.show', $pilot['campaign']))->assertInertia(fn (Assert $page) => $page->where('purchase', null));
});

test('opening a payment notification leads directly to checkout and marking read is idempotent', function () {
    $this->freezeSecond();
    $pilot = collectivePilot($this);
    $order = PurchaseOrder::where('participation_id', $pilot['participation']->id)->sole();
    $notification = $pilot['consumer']->notifications()->where('data->title', 'Pague a sua compra')->sole();
    expect($notification->data['link'])->toBe(route('consumidor.pedidos.checkout', $order, absolute: false));
    $this->actingAs($pilot['consumer'])->patch(route('consumidor.notificacoes.update', $notification->id), ['read' => true])->assertSessionHasNoErrors();
    $this->patch(route('consumidor.notificacoes.update', $notification->id), ['read' => true])->assertSessionHasNoErrors();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('legacy participation pages retain the product screen without changing their HTTP contract', function () {
    $campaign = Campaign::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('consumidor.campanhas.participar', ['campaignId' => $campaign->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('consumidor/campanhas/participar'));
});

test('consumer notification copy hides technical vocabulary without rewriting stored messages', function () {
    $consumer = User::factory()->create();
    $consumer->notify(new PurchaseUpdated(1, 'Reembolso integral confirmado pelo prestador de teste.'));
    $this->actingAs($consumer)->get(route('consumidor.notificacoes'))->assertInertia(fn (Assert $page) => $page->where('business.notifications.0.message', 'O valor total da compra foi devolvido.'));
    expect($consumer->notifications()->sole()->data['message'])->toBe('Reembolso integral confirmado pelo prestador de teste.');
});
