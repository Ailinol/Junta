<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('presentation refresh removes labels and adds local illustrations while preserving access and purchase amounts', function () {
    $this->freezeTime();
    config(['app.demo_mode' => true, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $user = User::factory()->create(['name' => '[Demo] Ana']);
    $access = $user->fresh()->getAttributes();
    $campaign = Campaign::factory()->create(['title' => '[DEMO] Arroz branco em sacos de 5 kg', 'image_url' => null, 'purchase_config' => ['commission_bps' => 250, 'delivery_shares_cents' => [17 => 6000], 'pickup_point' => '[DEMO] Maputo']]);
    $participation = Participation::factory()->for($campaign)->for($user)->create(['notes' => '[DEMO] Recolha no ponto']);
    $order = PurchaseOrder::factory()->for($participation)->create();
    $originalOrder = $order->fresh()->getAttributes();
    $supplier = Supplier::factory()->create(['company_name' => 'Comercial [DEMO]']);
    $id = (string) Str::uuid();
    DB::table('notifications')->insert(['id' => $id, 'type' => 'junta', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => json_encode(['message' => '[DEMO] Arroz disponível', 'amount' => 1234]), 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('junta:refresh-presentation')->assertSuccessful();

    expect($campaign->fresh()->title)->toBe('Arroz branco em sacos de 5 kg')
        ->and($campaign->fresh()->image_url)->toBe(url('/images/campaigns/product-rice-v1.svg'))
        ->and($campaign->fresh()->purchase_config)->toBe(['commission_bps' => 250, 'delivery_shares_cents' => [17 => 6000], 'pickup_point' => 'Maputo'])
        ->and($participation->fresh()->notes)->toBe('Recolha no ponto')
        ->and($supplier->fresh()->company_name)->toBe('Comercial')
        ->and($user->fresh()->name)->toBe('Ana')
        ->and($user->fresh()->getAttributes())->toBe([...$access, 'name' => 'Ana'])
        ->and($order->fresh()->getAttributes())->toBe($originalOrder)
        ->and(json_decode(DB::table('notifications')->where('id', $id)->value('data'), true))->toBe(['message' => 'Arroz disponível', 'amount' => 1234]);
    $before = DB::table('campaigns')->get()->toJson();
    $this->artisan('junta:refresh-presentation')->assertSuccessful();
    expect(DB::table('campaigns')->get()->toJson())->toBe($before);
});

test('presentation refresh preserves supplied images and does not invent images for unknown products', function () {
    config(['app.demo_mode' => true, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $supplied = Campaign::factory()->create(['title' => 'Arroz', 'image_url' => 'https://example.com/custom.jpg']);
    $unknown = Campaign::factory()->create(['title' => 'Produto específico sem imagem', 'image_url' => null]);

    $this->artisan('junta:refresh-presentation')->assertSuccessful();

    expect($supplied->fresh()->image_url)->toBe('https://example.com/custom.jpg')
        ->and($unknown->fresh()->image_url)->toBeNull();
});

test('presentation refresh refuses a non demonstration environment without changing records', function () {
    config(['app.demo_mode' => false, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $campaign = Campaign::factory()->create(['title' => '[DEMO] Arroz']);

    $this->artisan('junta:refresh-presentation')->assertFailed();

    expect($campaign->fresh()->title)->toBe('[DEMO] Arroz');
});

test('production presentation refresh requires explicit force', function () {
    config(['app.demo_mode' => true, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
    $this->app['env'] = 'production';
    $campaign = Campaign::factory()->create(['title' => '[DEMO] Arroz']);

    $this->artisan('junta:refresh-presentation')->assertFailed();

    expect($campaign->fresh()->title)->toBe('[DEMO] Arroz');
    $this->artisan('junta:refresh-presentation', ['--force' => true])->assertSuccessful();
    expect($campaign->fresh()->title)->toBe('Arroz');
});
