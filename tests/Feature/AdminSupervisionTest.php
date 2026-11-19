<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\OrganizerApplication;
use App\Models\PaymentAttempt;
use App\Models\PurchaseIssue;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use App\Models\User;
use App\Notifications\OrganizerApplicationDecision;
use App\Support\PurchaseProgress;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->freezeSecond();
    config(['inertia.ssr.enabled' => false, 'purchases.provider' => 'test', 'purchases.test_enabled' => true]);
});

test('the global administrative queue includes all five causes and ignores an ordinary payment before its deadline', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $payment = PurchaseOrder::factory()->create(['status' => 'payment_pending', 'payment_deadline' => now()->subHour()]);
    PaymentAttempt::factory()->create(['purchase_order_id' => $payment->id]);
    $refund = PurchaseOrder::factory()->create(['status' => 'refund_pending']);
    $problem = PurchaseOrder::factory()->create(['status' => 'paid']);
    PurchaseIssue::factory()->create(['purchase_order_id' => $problem->id]);
    $balance = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    FinancialEntry::factory()->create(['campaign_id' => $balance->id, 'kind' => 'supplier_due', 'amount_cents' => 10000]);
    $ready = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    PurchaseOrder::factory()->create(['campaign_id' => $ready->id, 'status' => 'paid', 'receipt_status' => 'received']);
    $ordinary = PurchaseOrder::factory()->create(['status' => 'payment_pending', 'payment_deadline' => now()->addDay()]);
    PaymentAttempt::factory()->create(['purchase_order_id' => $ordinary->id]);
    $this->actingAs($admin)->get(route('admin.compras.index', ['search' => 'NO MATCH', 'from' => '2000-01-01', 'to' => '2000-01-01']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selectedCampaignId', null)->where('interventions.total', 5)->where('orders.total', 0)
        ->where('interventions.data', fn ($rows): bool => collect($rows)->pluck('kind')->sort()->values()->all() === ['balance', 'issue', 'payment', 'ready', 'refund'])
        ->where('adminNavigation.interventions', 5));
    $this->get(route('admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('adminDashboard.tasks.total', 5)->has('adminDashboard.activity'));
});

test('intervention links identify the campaign and the exact order without exposing the private collection code', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $order = PurchaseOrder::factory()->create(['status' => 'payment_pending', 'payment_deadline' => now()->subHour()]);
    PaymentAttempt::factory()->create(['purchase_order_id' => $order->id]);
    $this->actingAs($admin)->get(route('admin.compras.index', ['campaignId' => $order->campaign_id, 'orderId' => $order->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selectedCampaignId', $order->campaign_id)->where('selectedOrder.id', $order->id)->where('selectedOrder.collectionCode', null)
        ->where('interventions.data.0.primaryAction.href', fn (string $href): bool => str_contains($href, 'campaignId='.$order->campaign_id) && str_contains($href, 'orderId='.$order->id))
        ->where('tab', 'pedidos'));
});

test('administrative history paginates the archive and filters operation and Maputo date boundaries', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    AuditEntry::factory()->count(103)->create(['action' => 'campaign.created', 'created_at' => '2026-11-18 00:00:00']);
    $included = AuditEntry::factory()->create(['action' => 'proposal.selected', 'created_at' => '2026-11-17 22:30:00']);
    AuditEntry::factory()->create(['action' => 'proposal.selected', 'created_at' => '2026-11-18 22:00:00']);
    $this->actingAs($admin)->get(route('admin.historico'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('adminHistory.records.total', 105)->has('adminHistory.records.data', 20));
    $this->get(route('admin.historico', ['action' => 'proposal.selected', 'from' => '2026-11-18', 'to' => '2026-11-18']))->assertInertia(fn (Assert $page) => $page->where('adminHistory.records.total', 1)->where('adminHistory.records.data.0.id', $included->id));
});

test('administrative supervision remains unavailable to non administrators', function () {
    $this->actingAs(User::factory()->create(['is_organizer' => true]));
    foreach (['admin.dashboard', 'admin.compras.index', 'admin.historico', 'admin.campanhas.index', 'admin.propostas.index', 'admin.fornecedores.index', 'admin.utilizadores.index', 'admin.candidaturas.index', 'admin.relatorios'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

test('campaign totals remain separate from movement filters and the CSV uses the same type and local dates', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create();
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'payment_received', 'amount_cents' => 15000, 'reference' => 'RECEIVED-ALL', 'created_at' => '2026-11-17 22:30:00']);
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'refund_paid', 'amount_cents' => 2000, 'reference' => 'MATCH-REFUND', 'created_at' => '2026-11-17 22:30:00']);
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'refund_paid', 'amount_cents' => 1000, 'reference' => 'OUTSIDE-DAY', 'created_at' => '2026-11-18 22:00:00']);
    $filters = ['campaignId' => $campaign->id, 'tab' => 'movimentos', 'type' => 'refund_paid', 'from' => '2026-11-18', 'to' => '2026-11-18'];
    $this->actingAs($admin)->get(route('admin.compras.index', $filters))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('campaignTotals.payment_received', 15000)->where('campaignTotals.refund_paid', 3000)->where('campaignTotals.net_received', 12000)
        ->where('filteredTotals.payment_received', 0)->where('filteredTotals.refund_paid', 2000)->where('filteredTotals.net_received', -2000)->where('events.total', 1));
    $csv = $this->get(route('admin.compras.export', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain('MATCH-REFUND')->not->toContain('RECEIVED-ALL')->not->toContain('OUTSIDE-DAY');
});

test('the closure checklist keeps the existing completion gate and points to the specific blockers', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    $waiting = PurchaseOrder::factory()->create(['campaign_id' => $campaign->id, 'status' => 'paid', 'receipt_status' => 'waiting']);
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'supplier_due', 'amount_cents' => 3000]);
    $this->actingAs($admin)->get(route('admin.compras.index', ['campaignId' => $campaign->id, 'tab' => 'encerramento']))->assertInertia(fn (Assert $page) => $page
        ->has('closure.items', 5)->where('closure.canComplete', false)->where('closure.items.0.complete', false)->where('closure.items.3.complete', false)
        ->where('closure.items.0.href', fn (string $href): bool => str_contains($href, 'orderId='.$waiting->id)));
    $waiting->update(['receipt_status' => 'received']);
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'supplier_settled', 'amount_cents' => 3000]);
    $this->get(route('admin.compras.index', ['campaignId' => $campaign->id]))->assertInertia(fn (Assert $page) => $page->where('closure.canComplete', true)->where('selectedOperations.canComplete', true));
});

test('final administrative orders have no actions while refunds in cancelled campaigns remain verifiable', function () {
    $campaign = Campaign::factory()->create(['purchase_phase' => 'cancelled', 'status' => 'cancelada']);
    $progress = app(PurchaseProgress::class);
    foreach (['refunded', 'cancelled'] as $status) {
        $order = PurchaseOrder::factory()->create(['campaign_id' => $campaign->id, 'status' => $status]);
        $state = $progress->administrativeOrder($order);
        expect($state['isFinal'])->toBeTrue()->and($state['canVerify'])->toBeFalse()->and($state['canResolve'])->toBeFalse();
    }
    $pending = PurchaseOrder::factory()->create(['campaign_id' => $campaign->id, 'status' => 'refund_pending']);
    $state = $progress->administrativeOrder($pending);
    expect($state['isFinal'])->toBeFalse()->and($state['canVerify'])->toBeTrue();
});

test('a review attempt is an intervention before the deadline and an empty dashboard has no tasks', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('adminDashboard.tasks.total', 0));
    $order = PurchaseOrder::factory()->create(['status' => 'payment_pending', 'payment_deadline' => now()->addDay()]);
    PaymentAttempt::factory()->create(['purchase_order_id' => $order->id, 'status' => 'review']);
    $this->get(route('admin.compras.index', ['orderId' => $order->id]))->assertInertia(fn (Assert $page) => $page->where('interventions.total', 1)->where('filters.campaignId', $order->campaign_id)->where('selectedCampaignId', $order->campaign_id));
});

test('administrative settlements reject excess balances and duplicate references without adding movements', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    SupplyOrder::factory()->create(['campaign_id' => $campaign->id]);
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'supplier_due', 'amount_cents' => 10000]);
    $payload = ['kind' => 'supplier_settled', 'amount' => '101.00', 'reference' => 'DOCUMENT-01', 'reason' => 'Comprovativo externo conferido'];
    $this->actingAs($admin)->post(route('admin.compras.record', $campaign), $payload)->assertSessionHasErrors('purchase');
    $this->assertDatabaseCount('financial_entries', 1);
    $this->post(route('admin.compras.record', $campaign), [...$payload, 'amount' => '40.00'])->assertSessionHasNoErrors();
    $this->post(route('admin.compras.record', $campaign), [...$payload, 'amount' => '40.00'])->assertSessionHasErrors('purchase');
    $this->assertDatabaseCount('financial_entries', 2);
});

test('completion refuses every unmet criterion before allowing a fully reconciled campaign', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create(['purchase_phase' => 'supply', 'status' => 'em_fornecimento']);
    $order = PurchaseOrder::factory()->create(['campaign_id' => $campaign->id, 'status' => 'paid', 'receipt_status' => 'waiting']);
    $this->actingAs($admin)->post(route('admin.compras.complete', $campaign))->assertSessionHasErrors('purchase');
    $order->update(['receipt_status' => 'received']);
    $issue = PurchaseIssue::factory()->create(['purchase_order_id' => $order->id]);
    $this->post(route('admin.compras.complete', $campaign))->assertSessionHasErrors('purchase');
    $issue->update(['status' => 'resolved']);
    $order->update(['status' => 'refund_pending']);
    $this->post(route('admin.compras.complete', $campaign))->assertSessionHasErrors('purchase');
    $order->update(['status' => 'refunded']);
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'carrier_due', 'amount_cents' => 2000]);
    $this->post(route('admin.compras.complete', $campaign))->assertSessionHasErrors('purchase');
    FinancialEntry::factory()->create(['campaign_id' => $campaign->id, 'kind' => 'carrier_settled', 'amount_cents' => 2000]);
    $this->post(route('admin.compras.complete', $campaign))->assertSessionHasNoErrors();
    expect($campaign->fresh()->purchase_phase)->toBe('completed');
});

test('application decisions require a rejection reason and notify the candidate', function (string $decision) {
    Notification::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $application = OrganizerApplication::factory()->create();
    if ($decision === 'rejected') {
        $this->actingAs($admin)->patch(route('admin.candidaturas.update', $application), ['decision' => $decision])->assertSessionHasErrors('reason');
        expect($application->fresh()->status)->toBe('pending');
    }
    $this->actingAs($admin)->patch(route('admin.candidaturas.update', $application), ['decision' => $decision, 'reason' => 'Decisão administrativa fundamentada'])->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe($decision)->and($application->fresh()->reviewed_by)->toBe($admin->id);
    Notification::assertSentTo($application->user, OrganizerApplicationDecision::class);
})->with(['approved', 'rejected']);

test('financial cancellation changes only payment and review campaigns', function (string $phase) {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create(['purchase_phase' => $phase]);
    $this->actingAs($admin)->post(route('admin.compras.cancel', $campaign), ['reason' => 'Cancelamento fundamentado'])->assertSessionHasNoErrors();
    expect($campaign->fresh()->purchase_phase)->toBe(in_array($phase, ['payment', 'review'], true) ? 'cancelled' : $phase);
})->with(['interest', 'payment', 'review', 'supply', 'completed', 'cancelled']);
