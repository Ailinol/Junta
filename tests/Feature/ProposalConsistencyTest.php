<?php

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;

/** @return array<string,mixed> */
function consistentOfferInput(Campaign $campaign, Supplier $supplier): array
{
    return ['campaignId' => $campaign->id, 'supplierId' => $supplier->id, 'unitPrice' => '100.50', 'availableQuantity' => 20, 'minimumQuantity' => 1, 'reservedUntil' => now()->addDays(3)->toIso8601String(), 'transportIncluded' => true, 'transportUnitPrice' => '0',
        'deliveryDays' => 3, 'deliveryMode' => 'recolha', 'validUntil' => now()->addDays(3)->toIso8601String(), 'status' => 'submetida'];
}

test('administration and supplier accept an offer during evaluation after interest has ended', function (bool $administrative) {
    $this->freezeTime();
    $supplierUser = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id, 'verified' => true]);
    $campaign = Campaign::factory()->create(['status' => 'em_avaliacao', 'deadline' => now()->subDay()]);
    $actor = $administrative ? User::factory()->create(['is_admin' => true]) : $supplierUser;
    $this->actingAs($actor)->post(route($administrative ? 'admin.propostas.store' : 'fornecedor.propostas.store'), consistentOfferInput($campaign, $supplier))
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('proposals', ['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id, 'status' => 'submetida']);
})->with([true, false]);

test('every write path rejects a second active offer for the same campaign and supplier', function (string $status, bool $administrative) {
    $supplierUser = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id, 'verified' => true]);
    $campaign = Campaign::factory()->create(['status' => 'em_avaliacao']);
    Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id, 'status' => $status]);
    $actor = $administrative ? User::factory()->create(['is_admin' => true]) : $supplierUser;
    $this->actingAs($actor)->post(route($administrative ? 'admin.propostas.store' : 'fornecedor.propostas.store'), consistentOfferInput($campaign, $supplier))
        ->assertSessionHasErrors('campaignId');
    $this->assertDatabaseCount('proposals', 1);
})->with(['rascunho', 'submetida', 'em_avaliacao', 'aceite'])->with([true, false]);

test('administrative update cannot reactivate a conflicting historical offer or move it into another campaign', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $supplier = Supplier::factory()->create(['verified' => true]);
    $campaign = Campaign::factory()->create();
    Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id]);
    $historic = Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id, 'status' => 'retirada']);
    $this->actingAs($admin)->patch(route('admin.propostas.update', $historic), consistentOfferInput($campaign, $supplier))->assertSessionHasErrors('campaignId');
    $other = Campaign::factory()->create();
    $this->patch(route('admin.propostas.update', $historic), consistentOfferInput($other, $supplier))->assertSessionHasErrors('campaignId');
    expect($historic->fresh()->status)->toBe('retirada')->and($historic->fresh()->campaign_id)->toBe($campaign->id);
});

test('an expired offer cannot be selected at the exact validity boundary and selection remains unique', function () {
    $this->freezeTime();
    $campaign = Campaign::factory()->create(['status' => 'em_avaliacao']);
    $expired = Proposal::factory()->create(['campaign_id' => $campaign->id, 'valid_until' => now()]);
    $first = Proposal::factory()->create(['campaign_id' => $campaign->id]);
    $second = Proposal::factory()->create(['campaign_id' => $campaign->id]);
    $this->actingAs($campaign->organizer)->post(route('organizador.campanhas.seleccionar', [$campaign, $expired]))->assertSessionHasErrors('proposal');
    $this->post(route('organizador.campanhas.seleccionar', [$campaign, $first]))->assertSessionHasNoErrors();
    $this->post(route('organizador.campanhas.seleccionar', [$campaign, $second]))->assertSessionHasErrors('proposal');
    expect($campaign->proposals()->where('status', 'aceite')->count())->toBe(1);
    expect($first->fresh()->status)->toBe('aceite');
});
