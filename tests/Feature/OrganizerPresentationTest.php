<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\Proposal;
use App\Models\PurchaseIssue;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use App\Models\User;
use App\Support\OrganizerData;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true, 'inertia.ssr.enabled' => false]);
});

test('organizer tasks prioritise expired deadlines and omit final campaigns', function () {
    $owner = User::factory()->create(['is_organizer' => true]);
    Campaign::factory()->count(9)->create(['organizer_id' => $owner->id, 'status' => 'rascunho']);
    $urgent = Campaign::factory()->create(['organizer_id' => $owner->id, 'deadline' => now()->subMinute()]);
    Campaign::factory()->create(['organizer_id' => $owner->id, 'purchase_phase' => 'completed', 'status' => 'concluida']);
    Campaign::factory()->create(['deadline' => now()->subDay()]);
    $this->actingAs($owner)->get(route('organizador.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('organizerDashboard.tasks', 8)->has('organizerDashboard.campaigns', 5)
        ->where('organizerDashboard.tasks.0.campaign.id', $urgent->id)
        ->where('organizerDashboard.tasks.0.state.primaryAction.code', 'close_interest')
        ->where('organizerDashboard.metrics.active', 1)->where('organizerDashboard.metrics.deliveries', 0)->has('business.campaigns', 0));
});

test('campaign list paginates twenty records in SQL and preserves search phase and order', function () {
    $owner = User::factory()->create(['is_organizer' => true]);
    Campaign::factory()->count(21)->create(['organizer_id' => $owner->id, 'location' => 'Matola', 'status' => 'rascunho']);
    Campaign::factory()->create(['organizer_id' => $owner->id, 'location' => 'Matola', 'status' => 'ativa']);
    Campaign::factory()->create(['location' => 'Matola', 'status' => 'rascunho']);
    $this->actingAs($owner)->get(route('organizador.campanhas.index', ['search' => 'Matola', 'phase' => 'draft', 'sort' => 'recent']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('organizerCampaigns.data', 20)->where('organizerCampaigns.total', 21)->where('organizerFilters.search', 'Matola')
        ->where('organizerCampaigns.next_page_url', fn (string $url): bool => str_contains($url, 'phase=draft') && str_contains($url, 'search=Matola')));
    $this->get(route('organizador.campanhas.index', ['search' => 'Matola', 'phase' => 'draft', 'sort' => 'recent', 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('organizerCampaigns.data', 1));
});

test('campaign search supports the business reference and title safely', function (string $search) {
    $owner = User::factory()->create(['is_organizer' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $owner->id, 'title' => "Milho d'Ouro"]);
    $term = $search === 'reference' ? 'JNT-'.str_pad((string) $campaign->id, 6, '0', STR_PAD_LEFT) : "Milho d'Ouro";
    $this->actingAs($owner)->get(route('organizador.campanhas.index', ['search' => $term]))->assertInertia(fn (Assert $page) => $page->where('organizerCampaigns.total', 1)->where('organizerCampaigns.data.0.campaign.id', $campaign->id));
})->with(['reference', 'title']);

test('organizer read models retain ownership and administrator access', function () {
    $campaign = Campaign::factory()->create();
    $this->get(route('organizador.campanhas.show', $campaign->id))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_organizer' => true]))->get(route('organizador.campanhas.show', $campaign->id))->assertNotFound();
    $this->get(route('organizador.campanhas.participantes', $campaign->id))->assertNotFound();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('organizador.campanhas.show', $campaign->id))->assertOk()->assertInertia(fn (Assert $page) => $page->where('organizerCampaign.campaign.id', $campaign->id));
});

test('selected supplier prerequisites explain the blocker without an open payment action', function (bool $complete, bool $expired) {
    $campaign = Campaign::factory()->create(['status' => 'proposta_selecionada']);
    Proposal::factory()->create(['campaign_id' => $campaign->id, 'status' => 'aceite', 'minimum_quantity' => $complete ? 2 : null, 'supplier_confirmed_at' => null, 'reserved_until' => now()->addDay(), 'valid_until' => $expired ? now()->subMinute() : now()->addDay()]);
    $this->actingAs($campaign->organizer)->get(route('organizador.compra.show', $campaign))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaign.state.primaryAction', null)->where('organizerCampaign.state.waitingOn.label', 'Decisão do organizador necessária')
        ->where('organizerCampaign.state.blocker', fn (string $cause): bool => str_contains($cause, $complete ? 'expiraram' : 'não tem')));
})->with([[false, false], [true, true]]);

test('complete selected legacy proposal exposes payments without supplier confirmation', function () {
    $campaign = Campaign::factory()->create(['status' => 'proposta_selecionada']);
    Proposal::factory()->create(['campaign_id' => $campaign->id, 'status' => 'aceite', 'minimum_quantity' => 2, 'supplier_confirmed_at' => null, 'reserved_until' => now()->addDay(), 'valid_until' => now()->addDays(2)]);
    $this->actingAs($campaign->organizer)->get(route('organizador.compra.show', $campaign))->assertInertia(fn (Assert $page) => $page->where('organizerCampaign.state.primaryAction.code', 'open_payments')->where('organizerCampaign.state.waitingOn', null)->where('organizerCampaign.deadline.value', now()->addDay()->toIso8601String()));
});

test('overdue uncertain payments name the actor and only claim notification after escalation', function (bool $escalated) {
    $campaign = Campaign::factory()->create(['purchase_phase' => 'review', 'status' => 'confirmada', 'payment_deadline' => now()->subHour()]);
    $participation = Participation::factory()->create(['campaign_id' => $campaign->id]);
    $order = PurchaseOrder::factory()->create(['participation_id' => $participation->id, 'status' => 'payment_pending', 'payment_deadline' => now()->subHour()]);
    PaymentAttempt::factory()->create(['purchase_order_id' => $order->id, 'escalated_at' => $escalated ? now() : null]);
    $response = $this->actingAs($campaign->organizer)->get(route('organizador.compra.show', $campaign))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->where('organizerCampaign.state.primaryAction', null)
        ->where('organizerCampaign.state.blocker', '1 pagamento por confirmar impede a verificação do mínimo.')
        ->where('organizerCampaign.state.description', fn (string $text): bool => str_contains($text, 'foi notificada') === $escalated)
        ->where('organizerCampaign.deadline.label', 'Prazo terminado')->where('orders.data.0.participantName', $participation->user->name)
        ->where('orders.data.0.state.label', 'A confirmar')->where('operations.canApure', false));
    $this->assertDatabaseCount('financial_entries', 0);
    expect($order->fresh()->status)->toBe('payment_pending');
})->with([false, true]);

test('partial distribution disables only orders exceeding available received goods', function () {
    $campaign = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    SupplyOrder::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 5, 'received_quantity' => 2]);
    $small = Participation::factory()->create(['campaign_id' => $campaign->id]);
    $large = Participation::factory()->create(['campaign_id' => $campaign->id]);
    PurchaseOrder::factory()->create(['participation_id' => $small->id, 'status' => 'paid', 'quantity' => 2]);
    PurchaseOrder::factory()->create(['participation_id' => $large->id, 'status' => 'paid', 'quantity' => 3]);
    $this->actingAs($campaign->organizer)->get(route('organizador.compra.show', $campaign))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaign.metrics.unallocatedUnits', 2)->where('orders.data.0.state.primaryAction.disabled', false)
        ->where('orders.data.1.state.primaryAction.disabled', true)
        ->where('orders.data.1.state.blocker', 'Este pedido precisa de 3 unidades; há 2 disponíveis. Registe a mercadoria em falta antes de separar.'));
});

test('final campaigns and orders have no next action', function (string $phase, string $status, string $orderStatus, string $label) {
    $campaign = Campaign::factory()->create(['purchase_phase' => $phase, 'status' => $status]);
    $participation = Participation::factory()->create(['campaign_id' => $campaign->id]);
    PurchaseOrder::factory()->create(['participation_id' => $participation->id, 'status' => $orderStatus, 'receipt_status' => $phase === 'completed' ? 'received' : 'waiting']);
    $this->actingAs($campaign->organizer)->get(route('organizador.compra.show', $campaign))->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaign.state.isFinal', true)->where('organizerCampaign.state.primaryAction', null)
        ->where('organizerCampaign.state.waitingOn', null)->where('organizerCampaign.deadline', null)
        ->where('orders.data.0.state.isFinal', true)->where('orders.data.0.state.primaryAction', null)->where('orders.data.0.state.label', $label));
})->with([['completed', 'concluida', 'paid', 'Entregue'], ['cancelled', 'cancelada', 'refunded', 'Reembolsado']]);

test('participants use names phone notes and one payment state instead of internal references', function () {
    $pilot = collectivePilot($this);
    $pilot['consumer']->forceFill(['name' => 'Ana Matavele', 'contact_preferences' => ['phone' => '+258841234567']])->save();
    $pilot['participation']->update(['notes' => 'Recolher depois das 16h.']);
    $this->actingAs($pilot['organizer'])->get(route('organizador.campanhas.participantes', $pilot['campaign']->id))->assertInertia(fn (Assert $page) => $page
        ->where('participants.0.name', 'Ana Matavele')->where('participants.0.phone', '+258841234567')
        ->where('participants.0.notes', 'Recolher depois das 16h.')->where('participants.0.state.label', 'Por pagar')->where('participants.0.amountCents', 31456)->missing('participants.0.reference'));
});

test('proposal highlights include only selectable offers and selection retains existing consequences', function () {
    $campaign = Campaign::factory()->create(['status' => 'em_avaliacao']);
    $valid = Proposal::factory()->create(['campaign_id' => $campaign->id, 'unit_price' => '100.00', 'delivery_days' => 5]);
    $expired = Proposal::factory()->create(['campaign_id' => $campaign->id, 'unit_price' => '1.00', 'delivery_days' => 1, 'valid_until' => now()->subMinute()]);
    $offers = collect(app(OrganizerData::class)->offers($campaign->refresh()))->keyBy('id');
    expect($offers[$valid->id]['bestPrice'])->toBeTrue();
    expect($offers[$valid->id]['fastest'])->toBeTrue();
    expect($offers[$expired->id]['canSelect'])->toBeFalse();
    expect($offers[$expired->id]['bestPrice'])->toBeFalse();
    $this->actingAs($campaign->organizer)->post(route('organizador.campanhas.seleccionar', [$campaign, $valid]))->assertSessionHasNoErrors();
    expect($campaign->fresh()->status)->toBe('proposta_selecionada');
    expect($valid->fresh()->status)->toBe('aceite');
    expect($expired->fresh()->status)->toBe('recusada');
});

test('creation saves the image and Maputo deadline through the existing endpoint', function () {
    $owner = User::factory()->create(['is_organizer' => true]);
    $input = ['title' => 'Arroz para o bairro', 'description' => 'Sacos selados de arroz branco.', 'category' => 'alimentacao', 'unit' => 'saco', 'imageUrl' => 'https://example.test/arroz.jpg', 'targetQuantity' => 12, 'estimatedPrice' => '120.50', 'deadline' => now()->addDays(2)->setTimezone('Africa/Maputo')->format('Y-m-d\TH:i'), 'location' => 'Matola', 'deliveryMode' => 'ambos', 'context' => 'Recolha ao sábado.', 'status' => 'ativa'];
    $this->actingAs($owner)->post(route('organizador.campanhas.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
    $campaign = Campaign::sole();
    expect($campaign->image_url)->toBe('https://example.test/arroz.jpg');
    expect($campaign->deadline->setTimezone('Africa/Maputo')->format('Y-m-d\TH:i'))->toBe($input['deadline']);
    $this->assertDatabaseCount('payment_attempts', 0);
});

test('a campaign closed before checkout gives its participants a final state', function (string $phase, string $status, string $label) {
    $campaign = Campaign::factory()->create(['purchase_phase' => $phase, 'status' => $status]);
    Participation::factory()->create(['campaign_id' => $campaign->id]);
    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.participantes', $campaign->id))->assertInertia(fn (Assert $page) => $page
        ->where('participants.0.state.isFinal', true)->where('participants.0.state.label', $label)->where('participants.0.state.primaryAction', null));
})->with([['cancelled', 'cancelada', 'Cancelado'], ['completed', 'concluida', 'Campanha concluída']]);

test('an unresolved order problem waits for administration instead of offering an unusable preparation task', function () {
    $campaign = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    SupplyOrder::factory()->create(['campaign_id' => $campaign->id, 'received_quantity' => 10]);
    $participation = Participation::factory()->create(['campaign_id' => $campaign->id]);
    $order = PurchaseOrder::factory()->create(['participation_id' => $participation->id, 'status' => 'paid']);
    PurchaseIssue::factory()->create(['purchase_order_id' => $order->id, 'user_id' => $order->user_id, 'status' => 'open']);
    $this->actingAs($campaign->organizer)->get(route('organizador.compra.show', $campaign))->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaign.state.primaryAction', null)->where('organizerCampaign.state.waitingOn.label', 'A aguardar a administração')->where('orders.data.0.state.primaryAction', null));
});
