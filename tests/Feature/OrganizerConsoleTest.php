<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use App\Models\User;
use App\Notifications\PlatformActivity;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
    config(['purchases.provider' => 'test', 'purchases.test_enabled' => true, 'inertia.ssr.enabled' => false]);
});

test('the home inbox includes every urgent task but excludes drafts and other owners', function () {
    $owner = User::factory()->create(['is_organizer' => true]);
    Campaign::factory()->count(9)->create(['organizer_id' => $owner->id, 'deadline' => now()->subDay()]);
    $first = Campaign::factory()->create(['organizer_id' => $owner->id, 'deadline' => now()->subDays(13)]);
    Campaign::factory()->count(7)->create(['organizer_id' => $owner->id, 'deadline' => now()->addDay()]);
    Campaign::factory()->create(['organizer_id' => $owner->id, 'status' => 'rascunho']);
    Campaign::factory()->create(['deadline' => now()->subMonth()]);
    $this->actingAs($owner)->get(route('organizador.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('organizerDashboard.inbox.count', 10)->has('organizerDashboard.inbox.tasks', 10)->has('organizerDashboard.inbox.waiting', 5)
        ->where('organizerDashboard.inbox.tasks.0.campaign.id', $first->id)
        ->where('organizerDashboard.inbox.tasks.0.workspace.deadline.label', 'terminou há 13 dias')
        ->where('organizerDashboard.inbox.tasks.0.workspace.deadline.blocked', false)
        ->where('organizerDashboard.inbox.tasks.0.workspace.responsible', 'Organizador'));
});

test('summary and the existing purchase endpoint expose the same authorized work and preserve the private code', function () {
    $pilot = collectivePilot($this);
    $order = PurchaseOrder::query()->where('campaign_id', $pilot['campaign']->id)->sole();
    foreach (['organizador.campanhas.show', 'organizador.compra.show'] as $route) {
        $this->actingAs($pilot['organizer'])->get(route($route, $pilot['campaign']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('organizerCampaign.workspace.code', 'payment')->where('organizerCampaign.workspace.progress.label', 'Pagos 0 de 3 do mínimo')
            ->where('organizerCampaign.workspace.primaryAction', null)->where('organizerCampaign.workspace.step', 5)
            ->where('participants.0.name', $pilot['consumer']->name)->where('orders.data.0.id', $order->id)->where('orders.data.0.collectionCode', null));
    }
    $this->actingAs(User::factory()->create(['is_organizer' => true]))->get(route('organizador.campanhas.show', $pilot['campaign']))->assertNotFound();
});

test('received goods derive distribution and keep partial preparation disabled with the cause', function () {
    $campaign = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    SupplyOrder::factory()->create(['campaign_id' => $campaign->id, 'quantity' => 48, 'received_quantity' => 48]);
    $part = Participation::factory()->create(['campaign_id' => $campaign->id]);
    PurchaseOrder::factory()->create(['participation_id' => $part->id, 'status' => 'paid', 'quantity' => 48, 'receipt_status' => 'ready']);
    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.show', $campaign))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaign.workspace.label', 'Em distribuição')->where('organizerCampaign.workspace.progress', null)->where('organizerCampaign.workspace.deadline', null));
    SupplyOrder::query()->where('campaign_id', $campaign->id)->update(['received_quantity' => 2]);
    PurchaseOrder::query()->where('campaign_id', $campaign->id)->update(['receipt_status' => 'waiting']);
    $this->get(route('organizador.campanhas.show', $campaign))->assertInertia(fn (Assert $page) => $page
        ->where('orders.data.0.canPrepare', false)->where('orders.data.0.state.primaryAction.disabled', true)
        ->where('orders.data.0.state.blocker', fn (string $cause): bool => str_contains($cause, 'há 2 disponíveis')));
});

test('an expired pending payment names the responsible actor without inventing a notification', function (bool $escalated) {
    $campaign = Campaign::factory()->create(['purchase_phase' => 'review', 'status' => 'confirmada', 'payment_deadline' => now()->subDays(2)]);
    $part = Participation::factory()->create(['campaign_id' => $campaign->id]);
    $order = PurchaseOrder::factory()->create(['participation_id' => $part->id, 'status' => 'payment_pending', 'payment_deadline' => now()->subDays(2)]);
    PaymentAttempt::factory()->create(['purchase_order_id' => $order->id, 'escalated_at' => $escalated ? now() : null]);
    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.show', $campaign))->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaign.workspace.primaryAction', null)->where('organizerCampaign.workspace.deadline.blocked', true)
        ->where('organizerCampaign.workspace.blocker', fn (string $cause): bool => str_contains($cause, 'sistema') && str_contains($cause, 'foi notificada') === $escalated));
})->with([false, true]);

test('final workspace states have no task deadline or progress', function (string $phase, string $status) {
    $campaign = Campaign::factory()->create(['purchase_phase' => $phase, 'status' => $status]);
    $this->actingAs($campaign->organizer)->get(route('organizador.campanhas.show', $campaign))->assertInertia(fn (Assert $page) => $page
        ->where('organizerCampaign.workspace.isFinal', true)->where('organizerCampaign.workspace.primaryAction', null)
        ->where('organizerCampaign.workspace.waitingOn', null)->where('organizerCampaign.workspace.progress', null)->where('organizerCampaign.workspace.deadline', null));
})->with([['completed', 'concluida'], ['cancelled', 'cancelada']]);

test('organizer notifications keep their own messages in business language and remove administration destinations', function () {
    $owner = User::factory()->create(['is_organizer' => true]);
    $owner->notify(new PlatformActivity('Compra em análise', 'O fornecimento aguarda conferência do prestador.', '/admin/compras/1', 'phase7-notification'));
    User::factory()->create()->notify(new PlatformActivity('Mensagem privada', 'Outro participante.', '/consumidor/dashboard', 'phase7-private'));
    $this->actingAs($owner)->get(route('notifications.index', ['area' => 'organizador']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('business.notifications', 1)->where('business.notifications.0.title', 'Compra em análise')->where('business.notifications.0.link', null)
        ->where('business.notifications.0.message', 'A mercadoria a caminho aguarda verificação do serviço de pagamento.'));
});
