<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\OrganizerApplication;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PlatformTestDataSeeder;
use Illuminate\Support\Facades\Hash;

test('test data provides usable accounts and coherent campaign scenarios without modifying existing records', function () {
    $this->freezeTime();
    $admin = User::factory()->create(['email' => 'lilianolicumba42@gmail.com', 'is_admin' => true]);
    $originalPassword = $admin->password;
    $realCampaign = Campaign::factory()->create(['title' => 'Campanha existente']);
    $this->seed(PlatformTestDataSeeder::class);

    expect($admin->fresh()->password)->toBe($originalPassword);
    expect($realCampaign->fresh()->title)->toBe('Campanha existente');
    expect(User::where('is_admin', true)->count())->toBe(1);
    expect(Campaign::where('title', 'like', '[TESTE]%')->count())->toBe(10);
    expect(Proposal::count())->toBe(12);
    expect(Participation::count())->toBe(24);
    expect(Supplier::where('verified', true)->count())->toBe(2);
    expect(Supplier::where('verified', false)->count())->toBe(1);

    $consumer = User::where('email', 'consumidor@junta.example.test')->firstOrFail();
    expect($consumer->is_organizer)->toBeFalse();
    expect(Hash::check(PlatformTestDataSeeder::PASSWORD, $consumer->password))->toBeTrue();
    expect($consumer->notifications()->count())->toBe(1);
    $this->post(route('login.store'), ['email' => $consumer->email, 'password' => PlatformTestDataSeeder::PASSWORD])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($consumer);

    $organizer = User::where('email', 'organizador@junta.example.test')->firstOrFail();
    expect($organizer->can('organize-campaigns'))->toBeTrue();
    expect(Campaign::where('title', 'like', '[TESTE]%')->where('organizer_id', '!=', $organizer->id)->exists())->toBeFalse();
    $review = Campaign::where('title', '[TESTE] Sementes para a próxima época')->firstOrFail();
    expect($review->participations()->sum('quantity'))->toBe(75);
    expect($review->proposals()->min('available_quantity'))->toBe(30);
    expect($review->proposals()->max('available_quantity'))->toBe(100);
    expect($review->deadline->isFuture())->toBeTrue();
    $this->actingAs($organizer)->get(route('organizador.campanhas.propostas', ['id' => $review->id]))->assertOk();
});

test('repeating test data preserves decisions passwords added participations and supplier verification', function () {
    $this->freezeTime();
    $admin = User::factory()->create(['email' => 'lilianolicumba42@gmail.com', 'is_admin' => true]);
    $this->seed(PlatformTestDataSeeder::class);
    $consumer = User::where('email', 'consumidor@junta.example.test')->firstOrFail();
    $consumer->update(['password' => 'Nova-Palavra!2026']);
    $newPassword = $consumer->fresh()->password;
    $open = Campaign::where('title', '[TESTE] Óleo de coco em grupo')->firstOrFail();
    $this->actingAs($consumer)->post(route('consumidor.campanhas.aderir', $open), ['quantity' => 8, 'notes' => 'Participação feita durante o teste'])->assertSessionHasNoErrors();

    $application = OrganizerApplication::where('status', 'pending')->firstOrFail();
    $this->actingAs($admin)->patch(route('admin.candidaturas.update', $application), ['decision' => 'approved', 'decisionReason' => 'Aprovação durante o teste'])->assertSessionHasNoErrors();
    $pendingSupplier = Supplier::where('verified', false)->firstOrFail();
    $pendingSupplier->update(['verified' => true]);
    $organizer = User::where('email', 'organizador@junta.example.test')->firstOrFail();
    $review = Campaign::where('title', '[TESTE] Sementes para a próxima época')->firstOrFail();
    $offer = $review->proposals()->where('available_quantity', 100)->firstOrFail();
    $this->actingAs($organizer)->post(route('organizador.campanhas.seleccionar', ['campaign' => $review, 'proposal' => $offer]))->assertSessionHasNoErrors();
    $counts = [User::count(), Campaign::count(), Proposal::count(), Participation::count(), AuditEntry::count()];
    $this->seed(PlatformTestDataSeeder::class);

    expect([User::count(), Campaign::count(), Proposal::count(), Participation::count(), AuditEntry::count()])->toBe($counts);
    expect($consumer->fresh()->password)->toBe($newPassword);
    expect($application->fresh()->status)->toBe('approved');
    expect($application->user->fresh()->is_organizer)->toBeTrue();
    expect($pendingSupplier->fresh()->verified)->toBeTrue();
    expect($review->fresh()->status)->toBe('proposta_selecionada');
    expect($offer->fresh()->status)->toBe('aceite');
    expect($open->participations()->where('user_id', $consumer->id)->value('quantity'))->toBe(8);
});

test('a conflicting real account aborts all test data instead of reusing or changing it', function () {
    $existing = User::factory()->create(['email' => 'candidato@junta.example.test', 'name' => 'Conta existente']);
    $password = $existing->password;
    expect(fn () => $this->seed(PlatformTestDataSeeder::class))->toThrow(RuntimeException::class);
    expect(User::count())->toBe(1);
    expect($existing->fresh()->name)->toBe('Conta existente');
    expect($existing->fresh()->password)->toBe($password);
    expect(Campaign::count())->toBe(0);
    expect(Supplier::count())->toBe(0);
});

test('test data refuses production before writing any record', function () {
    $this->app->instance('env', 'production');
    try {
        expect(fn () => app(PlatformTestDataSeeder::class)->run())->toThrow(RuntimeException::class);
        expect(User::count())->toBe(0);
        expect(Campaign::count())->toBe(0);
    } finally {
        $this->app->instance('env', 'testing');
    }
});
