<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string,mixed> */
function recoveredManagementCampaignInput(string $status = 'rascunho'): array
{
    return ['title' => 'Arroz branco em sacos de 25 kg', 'description' => 'Sacos selados, arroz branco de primeira qualidade.', 'category' => 'alimentacao', 'status' => $status, 'unit' => 'saco de 25 kg', 'targetQuantity' => 50, 'estimatedPrice' => '1200.50', 'deadline' => now()->addDays(8)->toDateString(), 'location' => 'Maputo, ponto central', 'deliveryMode' => 'entrega', 'context' => 'Procura do bairro'];
}

test('organizer can identify edit and publish a private campaign without creating a duplicate', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['is_organizer' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $owner->id, 'status' => 'rascunho']);
    $this->actingAs($owner)->get(route('organizador.campanhas.edit', $campaign))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('organizador/campanhas/criar')
            ->where('editingCampaignId', $campaign->id)->where('business.campaigns.0.canEdit', true));

    $this->patch(route('organizador.campanhas.update', $campaign), recoveredManagementCampaignInput('ativa'))
        ->assertSessionHasNoErrors()->assertRedirect(route('organizador.campanhas.show', ['id' => $campaign->id]));

    $this->assertDatabaseCount('campaigns', 1);
    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'title' => 'Arroz branco em sacos de 25 kg', 'status' => 'ativa', 'organizer_id' => $owner->id]);
    $this->assertDatabaseHas('audit_entries', ['action' => 'campaign.published', 'subject_id' => $campaign->id, 'user_id' => $owner->id]);
    $this->get(route('campanhas.show', ['id' => $campaign->id]))->assertInertia(fn (Assert $page) => $page->where('business.campaigns.0.reference', 'JNT-000001'));
});

test('only the owner or administrator can edit a campaign and an ordinary consumer has no management access', function () {
    $campaign = Campaign::factory()->create();
    $other = User::factory()->create(['is_organizer' => true]);
    $this->get(route('organizador.campanhas.edit', $campaign))->assertRedirect(route('login'));
    $this->actingAs($other)->get(route('organizador.campanhas.edit', $campaign))->assertNotFound();
    $this->patch(route('organizador.campanhas.update', $campaign), recoveredManagementCampaignInput())->assertForbidden();
    $this->actingAs(User::factory()->create())->patch(route('organizador.campanhas.update', $campaign), recoveredManagementCampaignInput())->assertForbidden();
    expect($campaign->fresh()->title)->toBe('Campanha de cimento');
});

test('campaign ownership cannot be reassigned through the organizer edit flow even by an administrator', function () {
    $campaign = Campaign::factory()->create(['status' => 'rascunho']);
    $admin = User::factory()->create(['is_admin' => true]);
    $ownerId = $campaign->organizer_id;
    $this->actingAs($admin)->patch(route('organizador.campanhas.update', $campaign), [...recoveredManagementCampaignInput(), 'organizerId' => $admin->id])
        ->assertSessionHasNoErrors();
    expect($campaign->fresh()->organizer_id)->toBe($ownerId);
});

test('campaign conditions are protected after participation or a supplier offer', function (string $dependency) {
    $this->withoutVite();
    $campaign = Campaign::factory()->create();
    if ($dependency === 'participation') {
        Participation::factory()->create(['campaign_id' => $campaign->id]);
    } else {
        Proposal::factory()->create(['campaign_id' => $campaign->id]);
    }
    $this->actingAs($campaign->organizer)->patch(route('organizador.campanhas.update', $campaign), recoveredManagementCampaignInput('ativa'))
        ->assertSessionHasErrors('campaign');
    expect($campaign->fresh()->title)->toBe('Campanha de cimento');
    $this->get(route('organizador.campanhas.edit', $campaign))->assertRedirect(route('organizador.campanhas.show', ['id' => $campaign->id]))->assertSessionHasErrors('campaign');
    $this->get(route('organizador.campanhas.show', ['id' => $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('business.campaigns.0.canEdit', false));
})->with(['participation', 'proposal']);

test('organizer edit cannot reopen closed campaigns or bypass supplier selection', function (string $status) {
    $campaign = Campaign::factory()->create(['status' => $status]);
    $this->actingAs($campaign->organizer)->patch(route('organizador.campanhas.update', $campaign), recoveredManagementCampaignInput('ativa'))
        ->assertSessionHasErrors('campaign');
    expect($campaign->fresh()->status)->toBe($status);
})->with(['em_avaliacao', 'proposta_selecionada', 'concluida', 'cancelada']);

test('public website receives no workspace data even when an administrator is logged in', function () {
    $this->withoutVite();
    $admin = User::factory()->create(['is_admin' => true]);
    Campaign::factory()->create(['organizer_id' => $admin->id, 'status' => 'rascunho']);
    $published = Campaign::factory()->create(['status' => 'ativa']);
    Participation::factory()->create(['campaign_id' => $published->id, 'user_id' => $admin->id]);
    Proposal::factory()->create(['campaign_id' => $published->id]);

    $this->actingAs($admin)->get(route('campanhas.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('business.campaigns', 1)->where('business.campaigns.0.id', $published->id)
        ->has('business.proposals', 0)->has('business.participations', 0)->has('business.notifications', 0)
        ->has('business.users', 0)->has('business.audit', 0)->has('business.applications', 0));
});

test('consumer discovery and campaign details stay inside the authenticated application', function () {
    $this->withoutVite();
    $campaign = Campaign::factory()->create();
    $this->get(route('consumidor.campanhas.show', ['id' => $campaign->id]))->assertRedirect(route('login'));
    $consumer = User::factory()->create();
    $this->actingAs($consumer)->get(route('consumidor.explorar'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('consumidor/explorar'));
    $this->get(route('consumidor.campanhas.show', ['id' => $campaign->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('consumidor/campanhas/detalhes')->has('business.campaigns', 1));
    $this->get(route('consumidor.campanhas.participar'))->assertOk();
});

test('campaign history includes its proposal events and excludes other campaigns and audit payloads', function () {
    $this->withoutVite();
    $campaign = Campaign::factory()->create();
    $proposal = Proposal::factory()->create(['campaign_id' => $campaign->id]);
    $other = Campaign::factory()->create();
    $created = AuditEntry::factory()->create(['subject_type' => 'Campaign', 'subject_id' => $campaign->id, 'action' => 'campaign.created', 'changes' => ['secret' => 'not-for-the-timeline']]);
    $selected = AuditEntry::factory()->create(['subject_type' => 'Proposal', 'subject_id' => $proposal->id, 'action' => 'proposal.selected']);
    AuditEntry::factory()->create(['subject_type' => 'Campaign', 'subject_id' => $other->id, 'action' => 'campaign.updated']);

    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.show', ['id' => $campaign->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('campaignHistory', 2)->where('campaignHistory.0.id', $selected->id)
            ->where('campaignHistory.1.id', $created->id)->missing('campaignHistory.1.changes'));
    $this->get(route('campanhas.show', ['id' => $campaign->id]))->assertInertia(fn (Assert $page) => $page->missing('campaignHistory'));
});
