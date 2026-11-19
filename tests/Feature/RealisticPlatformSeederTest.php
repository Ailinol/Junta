<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\OrganizerApplication;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\Proposal;
use App\Models\PurchaseIssue;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRefund;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Models\TestGatewayTransaction;
use App\Models\User;
use App\Support\CollectivePurchase;
use Database\Seeders\RealisticPlatformSeeder;
use Illuminate\Notifications\DatabaseNotification;

test('realistic data fills business areas with coherent purchases while preserving existing access and decisions', function () {
    $this->freezeTime();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['email' => 'organizador@junta.example.test', 'is_organizer' => true]);
    $consumer = User::factory()->create(['name' => '[TESTE] Consumidor', 'email' => 'consumidor@junta.example.test', 'is_organizer' => true]);
    $password = $consumer->password;
    $legacy = Campaign::factory()->for($owner, 'organizer')->create(['title' => '[TESTE] Campanha existente', 'status' => 'proposta_selecionada']);
    $accepted = Proposal::factory()->for($legacy)->create(['status' => 'aceite', 'notes' => '[TESTE] Transporte incluído; pagamento a combinar.']);
    $approvedCandidate = User::factory()->create(['email' => 'candidato@junta.example.test', 'is_organizer' => true]);
    $approvedApplication = OrganizerApplication::factory()->for($approvedCandidate)->create(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);

    $this->seed(RealisticPlatformSeeder::class);

    expect($consumer->fresh()->email)->toBe('consumidor@junta.example.test')
        ->and($consumer->fresh()->password)->toBe($password)
        ->and($consumer->fresh()->is_organizer)->toBeTrue()
        ->and($consumer->fresh()->name)->toBe('Ana Mucavele')
        ->and($legacy->fresh()->status)->toBe('proposta_selecionada')
        ->and($accepted->fresh()->status)->toBe('aceite')
        ->and($accepted->fresh()->notes)->toBe('Transporte incluído; pagamento a combinar.')
        ->and($approvedApplication->fresh()->status)->toBe('approved')
        ->and(OrganizerApplication::query()->where('status', 'pending')->exists())->toBeTrue();
    foreach ([OrganizerApplication::class, Supplier::class, Campaign::class, Participation::class, Proposal::class, PurchaseOrder::class, PaymentAttempt::class, PurchaseRefund::class, SupplyOrder::class, PurchaseIssue::class, FinancialEntry::class, TestGatewayTransaction::class, AuditEntry::class, DatabaseNotification::class] as $model) {
        expect($model::query()->exists())->toBeTrue($model.' should contain usable business data');
    }
    expect(Campaign::query()->where('title', 'like', '%[TESTE]%')->exists())->toBeFalse();
    $completed = Campaign::query()->where('purchase_phase', 'completed')->sole();
    $completedOrders = PurchaseOrder::query()->where('campaign_id', $completed->id);
    expect((clone $completedOrders)->where('status', 'paid')->where('receipt_status', 'received')->count())->toBeGreaterThan(0)
        ->and((clone $completedOrders)->whereNotNull('collection_code')->exists())->toBeFalse();
    $refunded = Campaign::query()->where('title', 'Fertilizante NPK em sacos de 50 kg — Matola')->sole();
    expect($refunded->purchase_phase)->toBe('cancelled')
        ->and(PurchaseOrder::query()->where('campaign_id', $refunded->id)->where('status', 'refunded')->count())->toBe(1);
    expect(PurchaseIssue::query()->where('status', 'open')->exists())->toBeTrue()
        ->and(PurchaseIssue::query()->where('status', 'resolved')->exists())->toBeTrue();

    $interest = Campaign::query()->where('title', 'Arroz agulha em sacos de 25 kg — Matola')->sole();
    app(CollectivePurchase::class)->closeInterest($owner, $interest);
    $counts = [User::count(), Campaign::count(), Proposal::count(), PurchaseOrder::count(), PaymentAttempt::count(), FinancialEntry::count(), DatabaseNotification::count()];
    $this->seed(RealisticPlatformSeeder::class);

    expect([User::count(), Campaign::count(), Proposal::count(), PurchaseOrder::count(), PaymentAttempt::count(), FinancialEntry::count(), DatabaseNotification::count()])->toBe($counts)
        ->and($interest->fresh()->status)->toBe('em_avaliacao')
        ->and($consumer->fresh()->password)->toBe($password);
});

test('realistic data refuses production before changing existing records', function () {
    $user = User::factory()->create(['name' => '[TESTE] Consumidor', 'email' => 'consumidor@junta.example.test', 'is_admin' => true]);
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->app->instance('env', 'production');
    try {
        expect(fn () => app(RealisticPlatformSeeder::class)->run())->toThrow(RuntimeException::class);
        expect($user->fresh()->name)->toBe('[TESTE] Consumidor')->and(Campaign::count())->toBe(0);
    } finally {
        $this->app->instance('env', 'testing');
    }
});

test('realistic data refuses a disabled payment provider without partially populating tables', function () {
    User::factory()->create(['is_admin' => true]);
    config(['purchases.provider' => 'disabled', 'purchases.test_enabled' => false]);

    expect(fn () => app(RealisticPlatformSeeder::class)->run())->toThrow(RuntimeException::class);

    expect(User::count())->toBe(1)->and(Campaign::count())->toBe(0)->and(PurchaseOrder::count())->toBe(0);
});

test('realistic data requires an existing administrator instead of creating privileged accounts', function () {
    User::factory()->create();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);

    expect(fn () => app(RealisticPlatformSeeder::class)->run())->toThrow(RuntimeException::class);

    expect(User::count())->toBe(1)->and(User::query()->where('is_admin', true)->exists())->toBeFalse()->and(Campaign::count())->toBe(0);
});
