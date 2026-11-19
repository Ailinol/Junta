<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true, 'inertia.ssr.enabled' => false]);
});

test('campaign sections contain authorized records in responsibility and urgency order', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['is_organizer' => true]);
    $recent = Campaign::factory()->create(['organizer_id' => $owner->id, 'deadline' => now()->subDay()]);
    $old = Campaign::factory()->create(['organizer_id' => $owner->id, 'deadline' => now()->subDays(13)]);
    Campaign::factory()->create(['organizer_id' => $owner->id]);
    Campaign::factory()->create(['organizer_id' => $owner->id, 'status' => 'rascunho']);
    Campaign::factory()->create(['organizer_id' => $owner->id, 'purchase_phase' => 'completed', 'status' => 'concluida']);
    Campaign::factory()->create(['deadline' => now()->subDays(20)]);
    $this->actingAs($owner)->get(route('organizador.campanhas.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('organizerCampaignSections', 4)
        ->where('organizerCampaignSections.0.key', 'attention')->where('organizerCampaignSections.0.campaigns.total', 2)
        ->where('organizerCampaignSections.0.campaigns.data.0.campaign.id', $old->id)
        ->where('organizerCampaignSections.0.campaigns.data.1.campaign.id', $recent->id)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.action.label', 'Fechar interesse')
        ->where('organizerCampaignSections.0.campaigns.data.0.list.deadline.label', 'terminou há 13 dias')
        ->where('organizerCampaignSections.0.campaigns.data.0.list.deadline.blocked', false)
        ->where('organizerCampaignSections.1.key', 'waiting')->where('organizerCampaignSections.1.campaigns.data.0.list.action', null)
        ->where('organizerCampaignSections.2.key', 'drafts')->where('organizerCampaignSections.2.collapsed', true)
        ->where('organizerCampaignSections.3.key', 'closed')->where('organizerCampaignSections.3.collapsed', true));
    $this->assertDatabaseCount('financial_entries', 0);
    expect($old->fresh()->status)->toBe('ativa');
});

test('human deadlines use Maputo calendar days with full dates only as detail', function (string $deadline, string $label) {
    $this->travelTo(now()->parse('2026-11-18 21:30:00', 'UTC'));
    $campaign = Campaign::factory()->create(['deadline' => $deadline]);
    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.index'))->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaignSections.0.campaigns.data.0.list.deadline.label', $label)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.deadline.blocked', false)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.deadline.detail', fn (string $detail): bool => str_contains($detail, 'Maputo')));
})->with([
    'tomorrow across UTC midnight' => ['2026-11-18T22:30:00Z', 'termina amanhã'],
    'today' => ['2026-11-18T21:45:00Z', 'termina hoje'],
    'yesterday' => ['2026-11-17T10:00:00Z', 'terminou ontem'],
    'thirteen days' => ['2026-11-05T10:00:00Z', 'terminou há 13 dias'],
]);

test('only elapsed deadlines blocked by uncertain payments are marked red', function (bool $expired, bool $pending, string $section, bool $blocked) {
    $this->freezeSecond();
    $campaign = Campaign::factory()->create(['purchase_phase' => 'payment', 'status' => 'confirmada', 'purchase_config' => ['minimum_quantity' => 6], 'payment_deadline' => $expired ? now()->subHour() : now()->addDay()]);
    $participation = Participation::factory()->create(['campaign_id' => $campaign->id]);
    $order = PurchaseOrder::factory()->create(['participation_id' => $participation->id, 'status' => $pending ? 'payment_pending' : 'paid']);
    if ($pending) {
        PaymentAttempt::factory()->create(['purchase_order_id' => $order->id]);
    }
    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.index'))->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaignSections.0.key', $section)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.deadline.blocked', $blocked)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.progress.label', $pending ? 'Pagos: 0 de 6 sacos' : 'Pagos: 1 de 6 sacos'));
    expect($order->fresh()->status)->toBe($pending ? 'payment_pending' : 'paid');
    $this->assertDatabaseCount('financial_entries', 0);
})->with([[true, true, 'waiting', true], [false, true, 'waiting', false], [true, false, 'attention', false]]);

test('supply labels and tasks reflect received goods and outstanding handoffs', function (int $received, string $receipt, string $label, string $section, ?string $action) {
    $campaign = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento', 'unit' => 'caixas']);
    $participation = Participation::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 48]);
    PurchaseOrder::factory()->create(['participation_id' => $participation->id, 'quantity' => 48, 'status' => 'paid', 'receipt_status' => $receipt]);
    SupplyOrder::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 48, 'received_quantity' => $received]);
    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.index'))->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaignSections.0.key', $section)
        ->where('organizerCampaignSections.0.campaigns.data.0.state.label', $label)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.action', $action === null ? null : fn (Collection $value): bool => $value['label'] === $action)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.deadline', null)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.progress.value', $received)
        ->where('organizerCampaignSections.0.campaigns.data.0.list.progress.target', 48));
})->with([
    'on the way' => [0, 'waiting', 'Mercadoria a caminho', 'waiting', null],
    'partial supply' => [10, 'waiting', 'Em distribuição', 'attention', 'Registar recepção'],
    'ready to prepare' => [48, 'waiting', 'Em distribuição', 'attention', 'Separar pedidos'],
    'A4 ready to hand off' => [48, 'ready', 'Em distribuição', 'attention', 'Entregar pedidos'],
]);

test('section pagination preserves filters without hiding campaigns needing action', function () {
    $this->freezeSecond();
    $owner = User::factory()->create(['is_organizer' => true]);
    Campaign::factory()->count(21)->create(['organizer_id' => $owner->id, 'status' => 'rascunho', 'location' => 'Matola']);
    $urgent = Campaign::factory()->create(['organizer_id' => $owner->id, 'deadline' => now()->subDay(), 'location' => 'Matola']);
    $this->actingAs($owner)->get(route('organizador.campanhas.index', ['search' => 'Matola']))->assertInertia(fn (Assert $page) => $page
        ->has('organizerCampaignSections', 2)->where('organizerCampaignSections.0.campaigns.data.0.campaign.id', $urgent->id)
        ->has('organizerCampaignSections.1.campaigns.data', 20)->where('organizerCampaignSections.1.campaigns.total', 21)
        ->where('organizerCampaignSections.1.campaigns.next_page_url', fn (string $url): bool => str_contains($url, 'page_drafts=2') && str_contains($url, 'search=Matola')));
    $this->get(route('organizador.campanhas.index', ['search' => 'Matola', 'page_drafts' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('organizerCampaignSections.1.campaigns.data', 1)->where('organizerCampaignSections.0.campaigns.data.0.campaign.id', $urgent->id));
    $this->get(route('organizador.campanhas.index', ['search' => 'sem resultado']))->assertInertia(fn (Assert $page) => $page->has('organizerCampaignSections', 0));
});
