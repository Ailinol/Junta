<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use App\Models\User;
use App\Support\CampaignForecast;
use App\Support\CollectivePurchase;
use Database\Seeders\EverydayProductsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true]);
});

function everydayProductsOwner(): User
{
    User::factory()->create(['is_admin' => true]);

    return User::factory()->create(['is_organizer' => true, 'email' => 'organizador@junta.example.test']);
}

test('everyday products add forty synthetic campaigns with coherent purchases and leave existing data intact', function () {
    $this->freezeTime();
    $owner = everydayProductsOwner();
    $consumer = User::factory()->create(['name' => '[TESTE] Nome escolhido', 'contact_preferences' => ['city' => 'Matola']]);
    $original = $consumer->refresh()->getAttributes();
    $existing = Campaign::factory()->for($owner, 'organizer')->create(['title' => '[TESTE] Campanha a preservar', 'status' => 'proposta_selecionada']);
    $oldOffer = Proposal::factory()->for($existing)->create(['status' => 'aceite', 'notes' => '[TESTE] Condições já revistas']);

    $this->seed(EverydayProductsSeeder::class);

    $this->assertDatabaseCount('campaigns', 41);
    $this->assertDatabaseHas('campaigns', ['title' => 'Arroz branco em sacos de 5 kg — Maputo', 'category' => 'alimentacao', 'status' => 'ativa']);
    $this->assertDatabaseHas('campaigns', ['title' => 'Papel higiénico em pacotes de 4 rolos — Nampula', 'category' => 'outro']);
    $this->assertDatabaseHas('campaigns', ['title' => 'Fraldas tamanho M em pacotes de 20 unidades — Maputo', 'category' => 'saude']);
    $this->assertDatabaseHas('campaigns', ['title' => 'Cadernos de 96 folhas em packs de 10 — Maputo', 'category' => 'escritorio']);
    $this->assertDatabaseHas('campaigns', ['id' => $existing->id, 'title' => '[TESTE] Campanha a preservar', 'status' => 'proposta_selecionada']);
    $this->assertDatabaseHas('proposals', ['id' => $oldOffer->id, 'status' => 'aceite', 'notes' => '[TESTE] Condições já revistas']);
    expect($consumer->fresh()->getAttributes())->toBe($original);
    expect(AuditEntry::where('changes->source', EverydayProductsSeeder::class)->where('action', 'campaign.created')->count())->toBe(40);
    $history = Campaign::where('purchase_phase', 'completed')->get();
    expect($history)->toHaveCount(5);
    expect($history->every(fn (Campaign $campaign): bool => $campaign->deadline->isPast()))->toBeTrue();
    expect(PurchaseOrder::whereIn('campaign_id', $history->modelKeys())->where('status', 'paid')->where('receipt_status', 'received')->count())->toBeGreaterThan(0);
    $this->assertDatabaseCount('organizer_applications', 0);
    Http::assertNothingSent();
});

test('repeating everyday products preserves progress edited titles and existing credentials without duplicates', function () {
    $this->freezeTime();
    $owner = everydayProductsOwner();
    $this->seed(EverydayProductsSeeder::class);
    $campaign = Campaign::where('title', 'Arroz branco em sacos de 5 kg — Maputo')->sole();
    app(CollectivePurchase::class)->closeInterest($owner, $campaign);
    $campaign->refresh()->update(['title' => 'Arroz revisto pelo organizador']);
    $password = $owner->password;
    $counts = [User::count(), Campaign::count(), Participation::count(), Proposal::count(), PurchaseOrder::count(), PaymentAttempt::count(), SupplyOrder::count(), FinancialEntry::count(), AuditEntry::count()];

    $this->seed(EverydayProductsSeeder::class);

    expect([User::count(), Campaign::count(), Participation::count(), Proposal::count(), PurchaseOrder::count(), PaymentAttempt::count(), SupplyOrder::count(), FinancialEntry::count(), AuditEntry::count()])->toBe($counts);
    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'title' => 'Arroz revisto pelo organizador', 'status' => 'em_avaliacao']);
    expect($owner->fresh()->password)->toBe($password);
    Http::assertNothingSent();
});

test('seeded demand provides rising and slow pace forecasts as well as sparse and reached target cases', function () {
    $this->freezeTime();
    everydayProductsOwner();
    $this->seed(EverydayProductsSeeder::class);
    config(['services.openai.enabled' => true, 'services.openai.key' => 'unused-seeder-test-key', 'services.openai.model' => 'gpt-6.1-terra']);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['model' => 'gpt-6.1-terra', 'status' => 'completed', 'output' => [
        ['type' => 'message', 'role' => 'assistant', 'status' => 'completed', 'content' => [['type' => 'output_text', 'text' => json_encode(['probability' => 0.6, 'expectedQuantity' => 100, 'lowerQuantity' => 45, 'upperQuantity' => 140], JSON_THROW_ON_ERROR)]]],
    ]])]);

    $fast = app(CampaignForecast::class)->analyse(Campaign::where('title', 'Arroz branco em sacos de 5 kg — Maputo')->sole());
    $slow = app(CampaignForecast::class)->analyse(Campaign::where('title', 'Feijão manteiga em sacos de 5 kg — Nampula')->sole());
    $sparse = app(CampaignForecast::class)->analyse(Campaign::where('title', 'Sal iodado em pacotes de 1 kg — Quelimane')->sole());
    $achieved = app(CampaignForecast::class)->analyse(Campaign::where('title', 'Farinha de milho em sacos de 25 kg — Matola')->sole());

    expect($fast['state'])->toBe('estimated');
    expect($fast['features']['elapsedHours'])->toBe(96.0);
    expect($fast['features']['recentUnitsPerDay'])->toBeGreaterThan($fast['features']['previousUnitsPerDay']);
    expect(count($fast['features']['retainedInterestSeries']))->toBeGreaterThanOrEqual(2);
    expect($slow['state'])->toBe('estimated');
    expect($slow['features']['recentUnitsPerDay'])->toBe(0.0);
    expect($sparse['state'])->toBe('insufficient_data');
    expect($achieved['state'])->toBe('target_reached');
    Http::assertSentCount(2);
});

test('everyday products refuse production and disabled simulated payments before inserting records', function (bool $production) {
    everydayProductsOwner();
    if ($production) {
        $this->app->instance('env', 'production');
    } else {
        config(['purchases.test_enabled' => false]);
    }

    try {
        expect(fn () => app(EverydayProductsSeeder::class)->run())->toThrow(RuntimeException::class);
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('users', 2);
    } finally {
        $this->app->instance('env', 'testing');
    }
    Http::assertNothingSent();
})->with(['production' => true, 'simulated payments disabled' => false]);
