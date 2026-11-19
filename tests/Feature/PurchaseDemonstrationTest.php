<?php

use App\Models\Campaign;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRefund;
use App\Models\SupplyOrder;
use App\Models\User;
use App\Support\CollectivePurchase;
use App\Support\TestPaymentGateway;
use Database\Seeders\CollectivePurchasePilotSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

function withPurchaseDemonstration(TestCase $test, Closure $callback): void
{
    $originalStorage = app()->storagePath();
    $originalConnection = DB::getDefaultConnection();
    $temporaryStorage = $originalStorage.'/app/purchase-demo-test-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($temporaryStorage.'/app');
    app()->useStoragePath($temporaryStorage);
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    try {
        $callback();
    } finally {
        DB::purge('purchase_demonstration');
        DB::setDefaultConnection($originalConnection);
        app()->useStoragePath($originalStorage);
        File::deleteDirectory($temporaryStorage);
    }
}

test('reset restores both starting scenarios and credentials without touching the normal connection', function () {
    $this->freezeTime();
    $existing = User::factory()->create();
    withPurchaseDemonstration($this, function () {
        $this->artisan('junta:demo-purchases', ['action' => 'reset'])->assertSuccessful();
        expect(DB::connection('purchase_demonstration')->table('campaigns')->pluck('purchase_phase')->all())->toBe(['interest', 'interest']);
        expect(DB::connection('purchase_demonstration')->table('participations')->pluck('quantity')->all())->toBe([3, 2]);
        expect(DB::connection('purchase_demonstration')->table('users')->count())->toBe(5);
        $this->artisan('junta:demo-purchases', ['action' => 'open', '--scenario' => 'success'])->assertSuccessful();
        DB::connection('purchase_demonstration')->table('users')->where('email', 'consumidor@pilot.junta.test')->update(['password' => Hash::make('changed')]);
        DB::connection('purchase_demonstration')->table('sessions')->insert(['id' => 'old-session', 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->artisan('junta:demo-purchases', ['action' => 'reset'])->assertSuccessful();

        $connection = DB::connection('purchase_demonstration');
        expect($connection->table('campaigns')->where('status', 'ativa')->whereNull('purchase_config')->count())->toBe(2);
        expect($connection->table('purchase_orders')->count())->toBe(0);
        expect($connection->table('payment_attempts')->count())->toBe(0);
        expect($connection->table('sessions')->count())->toBe(0);
        expect(Hash::check(CollectivePurchasePilotSeeder::PASSWORD, $connection->table('users')->where('email', 'consumidor@pilot.junta.test')->value('password')))->toBeTrue();
    });
    $this->assertModelExists($existing);
    $this->assertDatabaseCount('users', 1);
});

test('the demo payment reaches the configured outcome after the real deadline', function (string $scenario, int $campaignId, int $total) {
    $this->freezeTime();
    withPurchaseDemonstration($this, function () use ($scenario, $campaignId, $total) {
        $this->artisan('junta:demo-purchases', ['action' => 'reset'])->assertSuccessful();
        $this->artisan('junta:demo-purchases', ['action' => 'open', '--scenario' => $scenario, '--minutes' => 2])->assertSuccessful();
        $this->artisan('junta:demo-purchases', ['action' => 'open', '--scenario' => $scenario])->assertFailed();
        DB::setDefaultConnection('purchase_demonstration');
        $campaign = Campaign::findOrFail($campaignId);
        $consumer = User::where('email', 'consumidor@pilot.junta.test')->sole();
        $organizer = User::where('email', 'organizador@pilot.junta.test')->sole();
        $purchase = app(CollectivePurchase::class);
        $order = $purchase->issueOrder($consumer, $campaign, ['deliveryMethod' => 'recolha', 'termsVersion' => config('purchases.terms_version'), 'acceptTerms' => true]);
        expect($order->total_cents)->toBe($total);
        $attempt = $purchase->pay($consumer, $order);
        app(TestPaymentGateway::class)->simulate($attempt->reference, 'paid');
        $purchase->reconcile($attempt);
        expect($order->fresh()->status)->toBe('paid');
        expect(fn () => $purchase->closeCampaign($campaign, $organizer))->toThrow(ValidationException::class);
        $this->travel(3)->minutes();

        $purchase->closeCampaign($campaign->fresh(), $organizer);

        if ($scenario === 'success') {
            expect(SupplyOrder::sole()->quantity)->toBe(3);
            $purchase->receiveSupply($organizer, $campaign->fresh(), ['receivedQuantity' => 3, 'reference' => 'DEMO-TEST', 'reason' => 'Conferência fictícia.']);
            $purchase->prepareOrder($organizer, $order->fresh());
            expect($order->fresh()->collection_code)->toHaveLength(12);
            $purchase->handoff($organizer, $order->fresh(), $order->fresh()->collection_code);
            expect($order->fresh()->receipt_status)->toBe('received');
            expect($order->fresh()->collection_code)->toBeNull();
        } else {
            expect($order->fresh()->status)->toBe('refunded');
            expect(PurchaseRefund::sole()->amount_cents)->toBe(22521);
            expect(SupplyOrder::count())->toBe(0);
            $purchase->closeCampaign($campaign->fresh(), $organizer);
            expect(PurchaseRefund::count())->toBe(1);
        }
        expect(PurchaseOrder::count())->toBe(1);
    });
})->with(['collection' => ['success', 1, 33781], 'refund' => ['refund', 2, 22521]]);

test('demo operations refuse production or a disabled provider before resetting anything', function (bool $production) {
    withPurchaseDemonstration($this, function () use ($production) {
        if ($production) {
            $this->app->instance('env', 'production');
        } else {
            config(['purchases.test_enabled' => false]);
        }
        $this->artisan('junta:demo-purchases', ['action' => 'reset'])->assertFailed();
        expect(file_exists(storage_path('app/purchase-demonstration.sqlite')))->toBeFalse();
    });
})->with(['production' => true, 'disabled provider' => false]);

test('invalid demo options cannot open or reset a scenario', function () {
    withPurchaseDemonstration($this, function () {
        $this->artisan('junta:demo-purchases', ['action' => 'reset'])->assertSuccessful();
        $this->artisan('junta:demo-purchases', ['action' => 'open', '--scenario' => 'other'])->assertFailed();
        $this->artisan('junta:demo-purchases', ['action' => 'open', '--scenario' => 'success', '--minutes' => 0])->assertFailed();
        expect(DB::connection('purchase_demonstration')->table('campaigns')->whereNotNull('purchase_config')->count())->toBe(0);
    });
});
