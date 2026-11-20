<?php

use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard sends each access profile to its own workspace', function (bool $admin, bool $organizer, ?bool $supplierVerified, string $destination) {
    $user = User::factory()->create(['is_admin' => $admin, 'is_organizer' => $organizer]);
    if ($supplierVerified !== null) {
        Supplier::factory()->for($user)->create(['verified' => $supplierVerified]);
    }

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($destination));

    $this->assertAuthenticatedAs($user);
})->with([
    'consumer' => [false, false, null, 'consumidor.dashboard'],
    'organizer' => [false, true, null, 'organizador.dashboard'],
    'verified supplier' => [false, false, true, 'fornecedor.dashboard'],
    'pending supplier' => [false, false, false, 'fornecedor.dashboard'],
    'administrator' => [true, false, null, 'admin.dashboard'],
    'administrator with other profiles' => [true, true, true, 'admin.dashboard'],
    'organizer and supplier' => [false, true, true, 'organizador.dashboard'],
]);

test('a successful login arrives at the corresponding workspace without an area selector', function (string $profile, string $destination) {
    $user = User::factory()->create(['is_admin' => $profile === 'admin', 'is_organizer' => $profile === 'organizer']);
    if ($profile === 'supplier') {
        Supplier::factory()->for($user)->create(['verified' => true]);
    }

    $this->followingRedirects()->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component($destination));

    $this->assertAuthenticatedAs($user);
})->with([
    'consumer' => ['consumer', 'consumidor/dashboard'],
    'organizer' => ['organizer', 'organizador/dashboard'],
    'supplier' => ['supplier', 'fornecedor/dashboard'],
    'administrator' => ['admin', 'admin/dashboard'],
]);

test('dashboard routing still requires authentication and email verification', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('login preserves an intended destination when a consumer opens a protected campaign list', function () {
    $user = User::factory()->create();
    $destination = route('consumidor.campanhas.index');
    $this->get($destination)->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect($destination);

    $this->assertAuthenticatedAs($user);
});
