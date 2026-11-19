<?php

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;

function administrativeSupplierPayload(): array
{
    return ['companyName' => 'Fornecedor real', 'nuit' => '123456789', 'contactName' => 'Contacto', 'email' => 'contacto@example.test', 'phone' => '+258 84 123 4567', 'location' => 'Maputo', 'categories' => ['construcao'], 'description' => 'Materiais de construção.', 'verified' => false, 'userId' => null];
}
function administrativeProposalPayload(Campaign $campaign, Supplier $supplier): array
{
    return ['campaignId' => $campaign->id, 'supplierId' => $supplier->id, 'unitPrice' => 850, 'availableQuantity' => 100, 'totalEstimated' => 85000, 'deliveryDays' => 8, 'deliveryMode' => 'entrega', 'validUntil' => now()->addDays(5)->toDateString(), 'status' => 'submetida', 'notes' => 'Transporte a confirmar.'];
}
function administrativeCampaignPayload(User $owner): array
{
    return ['organizerId' => $owner->id, 'title' => 'Nova campanha', 'description' => 'Condições de compra.', 'category' => 'construcao', 'status' => 'ativa', 'unit' => 'sacos', 'targetQuantity' => 100, 'estimatedPrice' => 900, 'deadline' => now()->addDays(10)->toDateString(), 'location' => 'Maputo', 'deliveryMode' => 'recolha'];
}

test('administrator can create edit verify and remove an unlinked supplier with audit', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $account = User::factory()->create();
    $this->actingAs($admin)->post(route('admin.fornecedores.store'), administrativeSupplierPayload())->assertSessionHasNoErrors();
    $supplier = Supplier::firstOrFail();
    expect($supplier->verified)->toBeFalse();
    $this->patch(route('admin.fornecedores.update', $supplier), array_replace(administrativeSupplierPayload(), ['verified' => true, 'userId' => $account->id, 'companyName' => 'Nome actualizado']))->assertSessionHasNoErrors();
    expect($supplier->fresh()->verified)->toBeTrue();
    expect($supplier->fresh()->user_id)->toBe($account->id);
    $this->delete(route('admin.fornecedores.destroy', $supplier))->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    $this->assertDatabaseHas('audit_entries', ['action' => 'supplier.deleted', 'user_id' => $admin->id]);
});
test('only administrators can mutate suppliers proposals and campaigns', function () {
    $user = User::factory()->create(['is_organizer' => true]);
    $supplier = Supplier::factory()->create();
    $campaign = Campaign::factory()->create();
    $proposal = Proposal::factory()->create(['campaign_id' => $campaign->id]);
    $this->post(route('admin.fornecedores.store'), [])->assertRedirect(route('login'));
    $this->actingAs($user)->post(route('admin.fornecedores.store'), administrativeSupplierPayload())->assertForbidden();
    $this->patch(route('admin.fornecedores.update', $supplier), [])->assertForbidden();
    $this->delete(route('admin.fornecedores.destroy', $supplier))->assertForbidden();
    $this->post(route('admin.propostas.store'), [])->assertForbidden();
    $this->patch(route('admin.propostas.update', $proposal), [])->assertForbidden();
    $this->delete(route('admin.propostas.destroy', $proposal))->assertForbidden();
    $this->post(route('admin.campanhas.store'), [])->assertForbidden();
    $this->patch(route('admin.campanhas.update', $campaign), [])->assertForbidden();
    $this->delete(route('admin.campanhas.destroy', $campaign))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertForbidden();
});
test('administrator can manage a proposal but cannot forge acceptance or remove selected conditions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create();
    $supplier = Supplier::factory()->create(['verified' => true]);
    $payload = administrativeProposalPayload($campaign, $supplier);
    $this->actingAs($admin)->post(route('admin.propostas.store'), $payload)->assertSessionHasNoErrors();
    $proposal = Proposal::firstOrFail();
    $this->patch(route('admin.propostas.update', $proposal), array_replace($payload, ['unitPrice' => 880, 'totalEstimated' => 88000]))->assertSessionHasNoErrors();
    expect((float) $proposal->fresh()->unit_price)->toBe(880.0);
    $this->patch(route('admin.propostas.update', $proposal), array_replace($payload, ['status' => 'aceite']))->assertSessionHasErrors('status');
    $this->delete(route('admin.propostas.destroy', $proposal))->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('proposals', ['id' => $proposal->id]);
    $selected = Proposal::factory()->create(['campaign_id' => $campaign->id, 'status' => 'aceite']);
    $this->delete(route('admin.propostas.destroy', $selected))->assertSessionHasErrors('record');
    $this->assertDatabaseHas('proposals', ['id' => $selected->id]);
});
test('unverified suppliers closed campaigns and duplicate supplier identifiers are rejected', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create();
    $supplier = Supplier::factory()->create(['verified' => false]);
    $this->actingAs($admin)->post(route('admin.propostas.store'), administrativeProposalPayload($campaign, $supplier))->assertSessionHasErrors('supplierId');
    $supplier->verified = true;
    $supplier->save();
    $campaign->status = 'concluida';
    $campaign->save();
    $this->post(route('admin.propostas.store'), administrativeProposalPayload($campaign, $supplier))->assertSessionHasErrors('campaignId');
    $this->post(route('admin.fornecedores.store'), array_replace(administrativeSupplierPayload(), ['nuit' => $supplier->nuit]))->assertSessionHasErrors('nuit');
});
test('dependent records cannot be deleted or campaign execution invented', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['is_organizer' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $owner->id]);
    $supplier = Supplier::factory()->create(['verified' => true]);
    Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id]);
    $this->actingAs($admin)->delete(route('admin.fornecedores.destroy', $supplier))->assertSessionHasErrors('record');
    $this->delete(route('admin.campanhas.destroy', $campaign))->assertSessionHasErrors('record');
    $this->patch(route('admin.campanhas.update', $campaign), array_replace(administrativeCampaignPayload($owner), ['status' => 'concluida']))->assertSessionHasErrors('status');
    expect($campaign->fresh()->status)->toBe('ativa');
});
test('administrator creates a campaign for an approved organizer and edits or removes only valid records', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['is_organizer' => true]);
    $consumer = User::factory()->create();
    $this->actingAs($admin)->post(route('admin.campanhas.store'), administrativeCampaignPayload($consumer))->assertSessionHasErrors('organizerId');
    $this->post(route('admin.campanhas.store'), administrativeCampaignPayload($owner))->assertSessionHasNoErrors();
    $campaign = Campaign::firstOrFail();
    $this->patch(route('admin.campanhas.update', $campaign), array_replace(administrativeCampaignPayload($owner), ['title' => 'Título actualizado']))->assertSessionHasNoErrors();
    expect($campaign->fresh()->title)->toBe('Título actualizado');
    $this->delete(route('admin.campanhas.destroy', $campaign))->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
});
test('bootstrap administrator command does not create accounts or choose one implicitly', function () {
    $user = User::factory()->create();
    $this->artisan('junta:grant-admin', ['email' => 'missing@example.test'])->assertFailed();
    expect(User::count())->toBe(1);
    expect($user->fresh()->is_admin)->toBeFalse();
    $this->artisan('junta:grant-admin', ['email' => $user->email])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeTrue();
});

test('organizer creation endpoint cannot invent an execution state even for an administrator', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->post(route('organizador.campanhas.store'), array_replace(administrativeCampaignPayload($admin), ['status' => 'concluida']))->assertSessionHasErrors('status');
    expect(Campaign::count())->toBe(0);
});

test('computed proposal totals outside the supported range are validation errors', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create();
    $supplier = Supplier::factory()->create(['verified' => true]);
    $payload = array_replace(administrativeProposalPayload($campaign, $supplier), ['unitPrice' => 999999999999, 'availableQuantity' => 2147483647, 'totalEstimated' => null]);
    $this->actingAs($admin)->post(route('admin.propostas.store'), $payload)->assertSessionHasErrors('totalEstimated');
    expect(Proposal::count())->toBe(0);
});
