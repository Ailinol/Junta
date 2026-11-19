<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseActionRequest;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\PaymentAttempt;
use App\Models\PurchaseIssue;
use App\Models\PurchaseOrder;
use App\Support\AdminData;
use App\Support\CollectivePurchase;
use App\Support\Money;
use App\Support\PaymentGateway;
use App\Support\PurchaseData;
use App\Support\PurchaseFinanceFilters;
use App\Support\PurchaseProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseFinanceController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $filters = PurchaseFinanceFilters::validated($request);
        $query = PurchaseFinanceFilters::apply(FinancialEntry::query()->with('campaign'), $filters, true);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Não foi possível iniciar a exportação.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ID', 'Campanha', 'Tipo', 'Valor MZN', 'Referência', 'Motivo', 'Data'], ';', '"', '');
            foreach ($query->lazyById(200) as $entry) {
                $cells = [(string) $entry->id, $entry->campaign->title, $entry->kind, Money::decimal($entry->amount_cents), $entry->reference, $entry->reason, (string) $entry->getRawOriginal('created_at')];
                fputcsv($stream, array_map(fn (string $value): string => preg_match('/^[=+\-@\t\r\n]/u', ltrim($value, ' ')) ? "'".$value : $value, $cells), ';', '"', '');
            }
            fclose($stream);
        }, 'junta-financeiro-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function index(Request $request, PurchaseData $data, PurchaseProgress $progress, AdminData $admin): Response
    {
        $filters = PurchaseFinanceFilters::validated($request);
        $selection = $request->validate(['orderId' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->when($filters['campaignId'] !== null, fn ($rule) => $rule->where('campaign_id', $filters['campaignId']))]]);
        $selectedOrder = isset($selection['orderId']) ? PurchaseOrder::query()->with(['campaign', 'user', 'attempts.refund', 'issues'])->whereKey($request->integer('orderId'))->firstOrFail() : null;
        $selectedId = $filters['campaignId'] ?? $selectedOrder?->campaign_id;
        if ($selectedOrder !== null && $filters['campaignId'] === null) {
            $filters['campaignId'] = $selectedOrder->campaign_id;
        }
        $orders = PurchaseFinanceFilters::apply(PurchaseOrder::query(), $filters);
        $events = PurchaseFinanceFilters::apply(FinancialEntry::query(), $filters, true);
        $paid = PurchaseOrder::query()->whereIn('id', PaymentAttempt::query()->whereNotNull('confirmed_at')->select('purchase_order_id'));
        $totals = $this->totals($paid, FinancialEntry::query());
        $filteredTotals = $this->totals(PurchaseFinanceFilters::apply(clone $paid, $filters), clone $events);
        if (isset($selection['orderId'])) {
            $orders->whereKey($selection['orderId']);
        }
        $campaignViews = $admin->campaigns($request->user());
        $selectedCampaign = $selectedId === null ? null : Campaign::query()->findOrFail($selectedId);
        $selectedView = collect($campaignViews)->first(fn (array $view): bool => $view['campaign']['id'] === $selectedId);
        $purchaseViews = array_values(array_filter($campaignViews, fn (array $view): bool => $view['hasPurchase']));
        $balances = FinancialEntry::query()->select('campaign_id')->selectRaw("sum(case when kind in ('supplier_due','carrier_due') then amount_cents when kind in ('supplier_settled','carrier_settled') then -amount_cents else 0 end) as balance")->groupBy('campaign_id')->get()->keyBy('campaign_id');
        $purchaseViews = array_map(fn (array $view): array => [...$view, 'balanceCents' => (int) ($balances->get($view['campaign']['id'])?->getAttribute('balance') ?? 0)], $purchaseViews);
        $campaigns = Campaign::query()->whereNotNull('purchase_config')->latest('id')->get(['id', 'title', 'status', 'purchase_phase']);
        $operations = null;
        $closure = null;
        if ($selectedCampaign !== null) {
            $operations = $progress->operations($selectedCampaign);
            $closure = $progress->administrativeClosure($selectedCampaign, $operations);
        }
        $campaignTotals = $selectedId === null ? null : $this->totals((clone $paid)->where('campaign_id', $selectedId), FinancialEntry::query()->where('campaign_id', $selectedId));
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['pedidos', 'movimentos', 'encerramento'])]])['tab'] ?? 'pedidos';
        if (isset($selection['orderId'])) {
            $tab = 'pedidos';
        }
        $serializeOrder = fn (PurchaseOrder $order): array => [...$data->order($order), 'state' => $progress->administrativeOrder($order)];

        return Inertia::render('admin/compras', [
            'purchaseCampaigns' => $purchaseViews, 'selectedView' => $selectedView, 'tab' => $tab, 'closure' => $closure, 'campaignTotals' => $campaignTotals, 'eventTypes' => FinancialEntry::query()->distinct()->orderBy('kind')->pluck('kind')->all(), 'selectedOrder' => $selectedOrder ? $serializeOrder($selectedOrder) : null, 'testPayments' => app()->environment(['local', 'testing']) && config('purchases.provider') === 'test' && app(PaymentGateway::class)->available(),
            'search' => $filters['search'], 'filters' => $filters, 'filterTimezone' => 'Africa/Maputo',
            'totals' => $totals, 'totalsScope' => 'global', 'filteredTotals' => $filteredTotals, 'filteredTotalsScope' => 'current_filters',
            'amountsBasis' => 'confirmed_payments_before_refunds',
            'selectedCampaignId' => $selectedCampaign?->id, 'selectedOperations' => $operations,
            'orderId' => isset($selection['orderId']) ? (int) $selection['orderId'] : null,
            'interventions' => $admin->interventions($request->user()),
            'orders' => $orders->with(['campaign', 'user', 'attempts.refund', 'issues'])->latest('id')->paginate(20, ['*'], 'orders_page')->withQueryString()->through($serializeOrder),
            'events' => $events->latest('id')->paginate(20, ['*'], 'events_page')->withQueryString(),
            'campaigns' => $campaigns->map(fn (Campaign $campaign): array => ['id' => $campaign->id, 'title' => $campaign->title, 'status' => $campaign->status, 'purchase_phase' => $campaign->purchase_phase, 'effectiveStage' => $campaign->effectiveStage()])->all(),
        ]);
    }

    /** @param Builder<PurchaseOrder> $paid
     * @param  Builder<FinancialEntry>  $events
     * @return array<string,int>
     */
    private function totals(Builder $paid, Builder $events): array
    {
        $totals = [];
        foreach (['merchandise_cents', 'commission_cents', 'supplier_transport_cents', 'delivery_fee_cents'] as $column) {
            $totals[$column] = (int) (clone $paid)->sum($column);
        }
        foreach (['payment_received', 'refund_paid', 'supplier_due', 'carrier_due', 'supplier_settled', 'carrier_settled'] as $kind) {
            $totals[$kind] = (int) (clone $events)->where('kind', $kind)->sum('amount_cents');
        }
        $totals['net_received'] = $totals['payment_received'] - $totals['refund_paid'];

        return $totals;
    }

    public function record(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->finance($request->user(), $campaign, $request->validated());

        return back();
    }

    public function resolve(PurchaseActionRequest $request, PurchaseIssue $issue, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->resolveIssue($request->user(), $issue, $request->validated());

        return back();
    }
}
