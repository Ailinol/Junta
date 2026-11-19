<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('registration returns the selected field messages and preserves password validation', function () {
    Notification::fake();

    $this->from(route('register'))->post(route('register.store'), [
        'name' => '   ', 'email' => 'ana@', 'password' => 'Valida-Palavra!2026', 'password_confirmation' => 'Valida-Palavra!2026',
    ])->assertRedirect(route('register'))->assertSessionHasErrors([
        'name' => 'Informe o seu nome.', 'email' => 'Introduza um email válido.',
    ]);
    expect(User::count())->toBe(0);
    Notification::assertNothingSent();

    $this->post(route('register.store'), ['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'a', 'password_confirmation' => 'b'])
        ->assertSessionHasErrors('password')->assertSessionDoesntHaveErrors(['name', 'email']);
    expect(User::count())->toBe(0);
});

test('participation rejects raw invalid quantities before conversion and persists a corrected integer', function () {
    $consumer = User::factory()->create();
    $campaign = Campaign::factory()->create();
    foreach (['', '0', '-1', '1.5', '1,5'] as $quantity) {
        $this->actingAs($consumer)->post(route('consumidor.campanhas.aderir', $campaign), ['quantity' => $quantity])
            ->assertSessionHasErrors(['quantity' => 'A quantidade deve ser um número inteiro maior que zero.']);
    }
    expect(Participation::count())->toBe(0);

    $this->post(route('consumidor.campanhas.aderir', $campaign), ['quantity' => '1'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('participations', ['user_id' => $consumer->id, 'campaign_id' => $campaign->id, 'quantity' => 1]);
});

test('proposal returns price errors under unitPrice and persists only corrected data', function () {
    $supplier = Supplier::factory()->create(['verified' => true]);
    $campaign = Campaign::factory()->create();
    $payload = ['campaignId' => $campaign->id, 'availableQuantity' => '5', 'minimumQuantity' => '1', 'reservedUntil' => now()->addDays(5)->toDateString(), 'transportIncluded' => true, 'transportUnitPrice' => '0', 'deliveryDays' => '3', 'deliveryMode' => 'entrega', 'validUntil' => now()->addDays(5)->toDateString()];
    foreach (['', '0', '-1', 'abc'] as $price) {
        $this->actingAs($supplier->user)->post(route('fornecedor.propostas.store'), [...$payload, 'unitPrice' => $price])
            ->assertSessionHasErrors(['unitPrice' => 'O preço deve ser um número maior que zero.']);
    }
    expect(Proposal::count())->toBe(0);

    $this->post(route('fornecedor.propostas.store'), [...$payload, 'unitPrice' => '800.50'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('proposals', ['campaign_id' => $campaign->id, 'unit_price' => '800.50', 'total_estimated' => '4002.50']);
});

test('registration still rejects an existing email address', function () {
    $existing = User::factory()->create(['email' => 'ana@example.test']);

    $this->post(route('register.store'), ['name' => 'Ana', 'email' => $existing->email, 'password' => 'Valida-Palavra!2026', 'password_confirmation' => 'Valida-Palavra!2026'])
        ->assertSessionHasErrors('email');
    expect(User::count())->toBe(1);
});
