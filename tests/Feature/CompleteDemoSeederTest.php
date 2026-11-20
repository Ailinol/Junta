<?php

use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Participation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRefund;
use App\Models\SupplyOrder;
use App\Models\User;
use Database\Seeders\CompleteDemoSeeder;
use Database\Seeders\DatabaseSeeder;

test('complete demo builds coherent purchase stages and preserves the progress when seeded again', function () {
    $this->freezeTime();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->seed(DatabaseSeeder::class);
    expect(Campaign::count())->toBe(13);
    expect(User::count())->toBe(5);
    $payment = Campaign::where('title', 'Material escolar em kits')->sole();
    expect($payment->purchase_phase)->toBe('payment');
    expect(PurchaseOrder::where('campaign_id', $payment->id)->where('status', 'paid')->count())->toBe(1);
    $review = Campaign::where('title', 'Painéis solares de 100 W')->sole();
    expect($review->purchase_phase)->toBe('review');
    expect(SupplyOrder::where('campaign_id', $review->id)->exists())->toBeFalse();
    $supplied = Campaign::where('title', 'Lâmpadas LED em caixas de 10')->sole();
    expect(SupplyOrder::where('campaign_id', $supplied->id)->sole()->quantity)->toBe(5);
    $collection = Campaign::where('title', 'Papel A4 em caixas de 5 resmas')->sole();
    expect(PurchaseOrder::where('campaign_id', $collection->id)->where('receipt_status', 'ready')->count())->toBe(2);
    $completed = Campaign::where('title', 'Kits de primeiros socorros')->sole();
    expect($completed->purchase_phase)->toBe('completed');
    expect(PurchaseOrder::where('campaign_id', $completed->id)->where('receipt_status', 'received')->count())->toBe(2);
    $refunded = Campaign::where('title', 'Fertilizante NPK em sacos de 50 kg')->sole();
    expect($refunded->purchase_phase)->toBe('cancelled');
    expect(PurchaseOrder::where('campaign_id', $refunded->id)->where('status', 'refunded')->count())->toBe(1);
    expect(PurchaseRefund::where('status', 'refunded')->count())->toBe(1);

    $interest = Participation::whereHas('campaign', fn ($query) => $query->where('title', 'Arroz branco em sacos de 25 kg'))->firstOrFail();
    $interest->update(['quantity' => 9]);
    $orders = PurchaseOrder::count();
    $ledger = FinancialEntry::count();
    $password = User::where('email', 'consumidor@pilot.junta.test')->sole()->password;
    $this->seed(CompleteDemoSeeder::class);
    expect(Campaign::count())->toBe(13);
    expect(PurchaseOrder::count())->toBe($orders);
    expect(FinancialEntry::count())->toBe($ledger);
    expect($interest->fresh()->quantity)->toBe(9);
    expect(User::where('email', 'consumidor@pilot.junta.test')->sole()->password)->toBe($password);
});

test('demo seeding refuses an unavailable payment provider before creating accounts or records', function () {
    config(['purchases.provider' => 'disabled', 'purchases.test_enabled' => false]);
    expect(fn () => $this->seed(CompleteDemoSeeder::class))->toThrow(RuntimeException::class);
    expect(User::count())->toBe(0);
    expect(Campaign::count())->toBe(0);
});
