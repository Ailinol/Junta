<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\OrganizerApplication;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRefund;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Models\User;
use App\Support\CollectivePurchase;
use Database\Seeders\HostedDemoSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/** @return array<string, string> */
function hostedDemoPasswords(): array
{
    $passwords = [];
    foreach (array_keys(HostedDemoSeeder::PROFILES) as $role) {
        $passwords[$role] = 'Hosted-Demo-'.$role.'!2026';
    }

    return $passwords;
}

beforeEach(function () {
    $this->freezeTime();
    Http::preventStrayRequests();
    config(['app.demo_mode' => true, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
});

test('hosted demonstration fills business areas with isolated accounts for every access level', function () {
    $existing = User::factory()->create(['is_admin' => false, 'is_organizer' => false]);
    $original = $existing->fresh()->getAttributes();
    $passwords = hostedDemoPasswords();

    app(HostedDemoSeeder::class)->run($passwords);

    foreach (Campaign::all() as $campaign) {
        expect($campaign->title)->not->toContain('[DEMO]');
        $this->assertFileExists(public_path((string) parse_url($campaign->image_url, PHP_URL_PATH)));
    }
    expect(User::count())->toBe(10)->and(Campaign::count())->toBe(24)
        ->and($existing->fresh()->getAttributes())->toBe($original);
    foreach ($passwords as $role => $password) {
        $account = User::query()->where('email', $role.'@demo.junta.test')->firstOrFail();
        expect(Hash::check($password, $account->password))->toBeTrue()
            ->and($account->hasVerifiedEmail())->toBeTrue();
    }
    $admin = User::query()->where('email', 'admin@demo.junta.test')->firstOrFail();
    $owner = User::query()->where('email', 'organizador@demo.junta.test')->firstOrFail();
    $consumer = User::query()->where('email', 'consumidor@demo.junta.test')->firstOrFail();
    $seller = User::query()->where('email', 'fornecedor@demo.junta.test')->firstOrFail();
    expect(Gate::forUser($admin)->allows('administer-platform'))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('organize-campaigns'))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('administer-platform'))->toBeFalse()
        ->and(Gate::forUser($consumer)->allows('organize-campaigns'))->toBeFalse()
        ->and(Gate::forUser($consumer)->allows('supply-campaigns'))->toBeFalse()
        ->and(Gate::forUser($seller)->allows('supply-campaigns'))->toBeTrue()
        ->and(Supplier::query()->where('verified', false)->count())->toBe(1)
        ->and(OrganizerApplication::query()->pluck('status')->sort()->values()->all())->toBe(['approved', 'pending', 'rejected']);
    foreach ([Participation::class, Proposal::class, PurchaseOrder::class, PaymentAttempt::class, PurchaseRefund::class, SupplyOrder::class, FinancialEntry::class] as $model) {
        expect($model::query()->exists())->toBeTrue($model.' should contain demonstration data');
    }
    expect(Campaign::query()->where('purchase_phase', 'completed')->count())->toBe(2)
        ->and(Campaign::query()->where('purchase_phase', 'cancelled')->count())->toBe(1);
    $comparison = Campaign::query()->where('title', 'Arroz branco em sacos de 25 kg')->firstOrFail();
    expect($comparison->proposals()->count())->toBe(2);
});

test('repeating hosted demonstration preserves passwords profiles decisions and campaign progress', function () {
    $seeder = app(HostedDemoSeeder::class);
    $seeder->run(hostedDemoPasswords());
    $consumer = User::query()->where('email', 'consumidor@demo.junta.test')->firstOrFail();
    $consumer->update(['email' => 'consumidor-alterado@demo.junta.test', 'password' => 'Changed-Only-In-Test!2026']);
    $password = $consumer->fresh()->password;
    $candidate = User::query()->where('email', 'candidato@demo.junta.test')->firstOrFail();
    $candidate->forceFill(['is_organizer' => true])->save();
    $candidate->organizerApplication()->update(['status' => 'approved']);
    $owner = User::query()->where('email', 'organizador@demo.junta.test')->firstOrFail();
    $campaign = Campaign::query()->where('title', 'Arroz branco em sacos de 25 kg')->firstOrFail();
    app(CollectivePurchase::class)->closeInterest($owner, $campaign);
    $counts = [User::count(), Campaign::count(), Proposal::count(), PurchaseOrder::count(), FinancialEntry::count(), AuditEntry::count()];

    $seeder->run(hostedDemoPasswords());

    expect([User::count(), Campaign::count(), Proposal::count(), PurchaseOrder::count(), FinancialEntry::count(), AuditEntry::count()])->toBe($counts)
        ->and($consumer->fresh()->password)->toBe($password)
        ->and($consumer->fresh()->email)->toBe('consumidor-alterado@demo.junta.test')
        ->and($candidate->fresh()->is_organizer)->toBeTrue()
        ->and($candidate->organizerApplication()->first()->status)->toBe('approved')
        ->and($campaign->fresh()->status)->toBe('em_avaliacao');
});

test('hosted demonstration rolls back on reserved data conflicts without taking over existing accounts', function (string $conflict) {
    $existing = User::factory()->create(['email' => $conflict === 'email' ? 'admin@demo.junta.test' : 'existing@example.test']);
    if ($conflict === 'supplier') {
        Supplier::factory()->for($existing)->create(['nuit' => '999771001']);
    }
    if ($conflict === 'campaign') {
        Campaign::factory()->for($existing, 'organizer')->create(['title' => '[DEMO] Arroz branco em sacos de 25 kg']);
    }
    $before = [User::count(), Supplier::count(), Campaign::count(), AuditEntry::count()];
    $attributes = $existing->fresh()->getAttributes();

    expect(fn () => app(HostedDemoSeeder::class)->run(hostedDemoPasswords()))->toThrow(RuntimeException::class);

    expect([User::count(), Supplier::count(), Campaign::count(), AuditEntry::count()])->toBe($before)
        ->and($existing->fresh()->getAttributes())->toBe($attributes);
})->with(['email', 'supplier', 'campaign']);

test('hosted demonstration requires explicit demonstration and simulated payment switches', function (bool $demo, string $provider, bool $enabled) {
    config(['app.demo_mode' => $demo, 'purchases.provider' => $provider, 'purchases.test_enabled' => $enabled]);

    expect(fn () => app(HostedDemoSeeder::class)->run(hostedDemoPasswords()))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('users', 0);
})->with([[false, 'test', true], [true, 'disabled', true], [true, 'test', false]]);

test('hosted demonstration rejects production sqlite and missing credentials before writing', function () {
    expect(fn () => app(HostedDemoSeeder::class)->run([]))->toThrow(RuntimeException::class);
    $this->app->instance('env', 'production');
    try {
        expect(fn () => app(HostedDemoSeeder::class)->run(hostedDemoPasswords()))->toThrow(RuntimeException::class);
    } finally {
        $this->app->instance('env', 'testing');
    }
    $this->assertDatabaseCount('users', 0);
});

test('hosted demonstration command requires a private file and explicit force in production', function () {
    $this->artisan('junta:seed-demo')->assertFailed();
    $this->app->instance('env', 'production');
    try {
        $this->artisan('junta:seed-demo', ['--credentials' => 'unused.json'])->assertFailed();
    } finally {
        $this->app->instance('env', 'testing');
    }
    $this->assertDatabaseCount('users', 0);
});

test('hosted demonstration command reads private credentials without printing passwords', function () {
    $file = tempnam(sys_get_temp_dir(), 'junta-demo-test-');
    $accounts = [];
    foreach (hostedDemoPasswords() as $role => $password) {
        $accounts[$role] = ['password' => $password];
    }
    file_put_contents($file, json_encode(['accounts' => $accounts], JSON_THROW_ON_ERROR));
    try {
        $this->artisan('junta:seed-demo', ['--credentials' => $file])
            ->doesntExpectOutputToContain($accounts['admin']['password'])->assertSuccessful();
        $this->assertDatabaseCount('campaigns', 24);
    } finally {
        unlink($file);
    }
});

test('hosted demonstration command rejects malformed or incomplete credential files without creating accounts', function (string $contents) {
    $file = tempnam(sys_get_temp_dir(), 'junta-demo-test-');
    file_put_contents($file, $contents);
    try {
        $this->artisan('junta:seed-demo', ['--credentials' => $file])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    } finally {
        unlink($file);
    }
})->with(['{"accounts":', '{}', '{"accounts":{"admin":{"password":12}}}']);
