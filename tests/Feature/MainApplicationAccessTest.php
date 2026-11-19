<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\MainApplicationAccessSeeder;
use Illuminate\Support\Facades\Hash;

test('the requested local account can log in and only opens its authorised workspace', function (string $profile, string $allowed, string $denied) {
    $this->seed(MainApplicationAccessSeeder::class);
    $email = $profile.'@pilot.junta.test';
    $account = User::query()->where('email', $email)->sole();

    $this->post(route('login.store'), ['email' => $email, 'password' => MainApplicationAccessSeeder::PASSWORD])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($account);
    $this->get(route($allowed))->assertOk();
    $this->get(route($denied))->assertForbidden();
})->with([
    'consumidor' => ['consumidor', 'consumidor.dashboard', 'admin.dashboard'],
    'organizador' => ['organizador', 'organizador.dashboard', 'admin.dashboard'],
    'fornecedor' => ['fornecedor', 'fornecedor.dashboard', 'admin.dashboard'],
    'admin' => ['admin', 'admin.dashboard', 'fornecedor.dashboard'],
]);

test('preparing main application access preserves existing credentials permissions supplier decisions and campaigns', function () {
    $organizer = User::factory()->unverified()->create(['email' => 'organizador@pilot.junta.test', 'name' => 'Nome escolhido pela pessoa', 'password' => 'Acesso-Alterado!2026', 'is_organizer' => false]);
    $passwordHash = $organizer->password;
    $campaign = Campaign::factory()->for($organizer, 'organizer')->create(['status' => 'proposta_selecionada']);
    $supplierUser = User::factory()->create(['email' => 'fornecedor@pilot.junta.test']);
    $supplier = Supplier::factory()->for($supplierUser)->create(['verified' => false]);

    $this->seed(MainApplicationAccessSeeder::class);
    $counts = [User::count(), Supplier::count(), AuditEntry::count()];
    $this->seed(MainApplicationAccessSeeder::class);

    expect([User::count(), Supplier::count(), AuditEntry::count()])->toBe($counts)
        ->and($organizer->fresh()->name)->toBe('Nome escolhido pela pessoa')
        ->and($organizer->fresh()->password)->toBe($passwordHash)
        ->and(Hash::check('Acesso-Alterado!2026', $organizer->fresh()->password))->toBeTrue()
        ->and($organizer->fresh()->is_organizer)->toBeFalse()
        ->and($organizer->fresh()->email_verified_at)->toBeNull()
        ->and($supplier->fresh()->verified)->toBeFalse()
        ->and($campaign->fresh()->status)->toBe('proposta_selecionada');
    $this->assertDatabaseCount('campaigns', 1);
});

test('main application access is blocked in production before any account is created', function () {
    User::factory()->create();
    $this->app->instance('env', 'production');
    try {
        expect(fn () => app(MainApplicationAccessSeeder::class)->run())->toThrow(RuntimeException::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('suppliers', 0);
    } finally {
        $this->app->instance('env', 'testing');
    }
});

test('a supplier identity conflict rolls back all newly prepared accounts', function () {
    $supplier = Supplier::factory()->create(['nuit' => '991305105']);
    $userCount = User::count();

    expect(fn () => app(MainApplicationAccessSeeder::class)->run())->toThrow(RuntimeException::class);

    expect(User::count())->toBe($userCount)->and(AuditEntry::count())->toBe(0);
    $this->assertModelExists($supplier);
    expect(User::query()->where('email', 'like', '%@pilot.junta.test')->exists())->toBeFalse();
});
