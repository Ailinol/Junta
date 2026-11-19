<?php

use App\Models\FinancialEntry;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Support\TestPaymentGateway;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['inertia.ssr.enabled' => false]);
});

test('production simulations remain unavailable unless every demonstration switch is enabled', function (bool $demoMode, string $provider, bool $testEnabled) {
    config(['app.demo_mode' => $demoMode, 'purchases.provider' => $provider, 'purchases.test_enabled' => $testEnabled]);
    $this->app->instance('env', 'production');

    try {
        expect(fn () => app(TestPaymentGateway::class)->initiate('blocked-demo', 100, 'MZN'))
            ->toThrow(ValidationException::class);
        $this->postJson(route('payments.test.events'), [])->assertNotFound();
        $this->assertDatabaseCount('test_gateway_transactions', 0);
        $this->assertDatabaseCount('financial_entries', 0);
    } finally {
        $this->app->instance('env', 'testing');
    }
})->with([
    'ordinary production' => [false, 'test', true],
    'disabled provider' => [true, 'disabled', true],
    'simulations disabled' => [true, 'test', false],
]);

test('a production demonstration confirms a simulated payment and reaches the supply phase through the scheduler', function () {
    Http::preventStrayRequests();
    $this->freezeSecond();
    config(['app.demo_mode' => true, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->app->instance('env', 'production');

    try {
        $pilot = collectivePilot($this);
        $order = pilotOrder($this, $pilot);
        $attempt = pilotPayment($this, $pilot, $order);

        $this->get(route('consumidor.pedidos.checkout', $order))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('purchase.testTools', true)->where('purchase.checkoutAvailable', true));
        $this->post(route('consumidor.pedidos.simulate', $order), ['status' => 'paid'])->assertSessionHasNoErrors();

        expect($attempt->fresh()->status)->toBe('paid');
        expect($order->fresh()->status)->toBe('paid');
        $this->assertDatabaseHas('financial_entries', ['purchase_order_id' => $order->id, 'kind' => 'payment_received', 'amount_cents' => 31456]);

        $this->travel(25)->hours();
        $this->artisan('junta:reconcile-purchases')->assertSuccessful();
        expect($pilot['campaign']->fresh()->purchase_phase)->toBe('supply');
        $this->assertDatabaseHas('supply_orders', ['campaign_id' => $pilot['campaign']->id, 'quantity' => 3]);
    } finally {
        $this->app->instance('env', 'testing');
    }
});

test('a production demonstration refunds the full simulated payment when the minimum is missed', function () {
    Http::preventStrayRequests();
    $this->freezeSecond();
    config(['app.demo_mode' => true, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->app->instance('env', 'production');

    try {
        $pilot = collectivePilot($this, minimum: 5);
        $order = pilotOrder($this, $pilot);
        pilotPayment($this, $pilot, $order);
        $this->post(route('consumidor.pedidos.simulate', $order), ['status' => 'paid'])->assertSessionHasNoErrors();

        $this->travel(25)->hours();
        $this->artisan('junta:reconcile-purchases')->assertSuccessful();
        $this->artisan('junta:reconcile-purchases')->assertSuccessful();

        expect($order->fresh()->status)->toBe('refunded');
        expect($pilot['campaign']->fresh()->purchase_phase)->toBe('cancelled');
        $this->assertDatabaseHas('purchase_refunds', ['amount_cents' => 31456, 'status' => 'refunded']);
        expect(FinancialEntry::query()->where('kind', 'refund_paid')->count())->toBe(1);
        $this->assertDatabaseCount('supply_orders', 0);
    } finally {
        $this->app->instance('env', 'testing');
    }
});

test('demonstration simulation still requires the order owner and a signed webhook', function () {
    $this->freezeSecond();
    config(['app.demo_mode' => true, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->app->instance('env', 'production');

    try {
        $pilot = collectivePilot($this);
        $order = pilotOrder($this, $pilot);
        $attempt = pilotPayment($this, $pilot, $order);
        $this->actingAs(User::factory()->create())->post(route('consumidor.pedidos.simulate', $order), ['status' => 'paid'])->assertNotFound();
        auth()->logout();
        $this->post(route('consumidor.pedidos.simulate', $order), ['status' => 'paid'])->assertRedirect(route('login'));
        $this->postJson(route('payments.test.events'), [])->assertForbidden();
        expect($attempt->fresh()->status)->toBe('pending');
        expect($order->fresh()->status)->toBe('payment_pending');
        $this->assertDatabaseCount('financial_entries', 0);
    } finally {
        $this->app->instance('env', 'testing');
    }
});

test('intentionally disabled payments skip reconciliation without changing pending records', function () {
    $attempt = PaymentAttempt::factory()->create(['next_check_at' => null]);
    config(['purchases.provider' => 'disabled']);

    $this->artisan('junta:reconcile-purchases')->expectsOutput('Pagamentos desativados. Reconciliação ignorada.')->assertSuccessful();

    expect($attempt->fresh()->next_check_at)->toBeNull();
    $this->assertDatabaseCount('financial_entries', 0);
});

test('a misconfigured demonstration provider still reports a reconciliation failure', function () {
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => false]);

    $this->artisan('junta:reconcile-purchases')->assertFailed();
});

test('production demonstration registration enforces strong passwords without external verification', function () {
    Http::preventStrayRequests();
    Notification::fake([VerifyEmail::class]);
    config(['app.demo_mode' => true]);
    $this->app->instance('env', 'production');

    try {
        $data = ['name' => 'Avaliador fictício', 'email' => 'avaliador@junta.test', 'password' => 'weak', 'password_confirmation' => 'weak'];
        $this->post(route('register.store'), $data)->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);

        $this->post(route('register.store'), [...$data, 'password' => 'Junta-Demo!LongPassword2026', 'password_confirmation' => 'Junta-Demo!LongPassword2026'])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'avaliador@junta.test')->sole();
        expect($user->email_verified_at)->toBeNull();
        Notification::assertSentTo($user, VerifyEmail::class);
        Http::assertNothingSent();
    } finally {
        $this->app->instance('env', 'testing');
    }
});

test('ordinary production still rejects a compromised password before creating an account', function () {
    Http::preventStrayRequests();
    $password = 'Junta-Demo!LongPassword2026';
    $hash = strtoupper(sha1($password));
    Http::fake(['https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).':99', 200)]);
    config(['app.demo_mode' => false]);
    $this->app->instance('env', 'production');

    try {
        $this->post(route('register.store'), ['name' => 'Avaliador fictício', 'email' => 'avaliador@junta.test', 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
        Http::assertSentCount(1);
    } finally {
        $this->app->instance('env', 'testing');
    }
});
