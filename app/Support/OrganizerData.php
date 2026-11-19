<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\SupplyOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/** Authorized, read-only projections for the organizer workspace. */
class OrganizerData
{
    public function __construct(private PlatformData $platform, private PurchaseProgress $progress, private PurchaseData $purchases, private CampaignForecast $forecast) {}

    /** @return Builder<Campaign> */
    private function query(User $user): Builder
    {
        return Campaign::query()->when(! $user->is_admin, fn (Builder $query) => $query->where('organizer_id', $user->id))
            ->with('organizer')->with(['proposals' => fn ($query) => $query->where('status', 'aceite')->with('supplier')])
            ->withExists(['participations', 'proposals'])
            ->withCount(['participations as participant_count' => fn (Builder $query) => $query->where('status', '!=', 'cancelada'), 'proposals',
                'proposals as open_proposals_count' => fn (Builder $query) => $query->whereIn('status', ['submetida', 'em_avaliacao'])->where('valid_until', '>', now())->whereHas('supplier', fn (Builder $supplier) => $supplier->where('verified', true))])
            ->withSum(['participations as current_quantity' => fn (Builder $query) => $query->where('status', '!=', 'cancelada')], 'quantity');
    }

    /** @param Builder<Campaign> $query
     * @return Builder<Campaign>
     */
    private function urgent(Builder $query): Builder
    {
        return $query->orderByRaw("case
            when purchase_phase in ('completed','cancelled') or status in ('concluida','cancelada') then 9
            when status = 'ativa' and deadline <= ? then 0
            when purchase_phase in ('payment','review') and payment_deadline <= ? and not exists
                (select 1 from payment_attempts pa join purchase_orders po on po.id = pa.purchase_order_id where po.campaign_id = campaigns.id and pa.status in ('pending','review')) then 0
            when status = 'em_avaliacao' and exists (select 1 from proposals p join suppliers s on s.id = p.supplier_id where p.campaign_id = campaigns.id and p.status in ('submetida','em_avaliacao') and p.valid_until > ? and s.verified = 1) then 1
            when purchase_phase = 'supply' and exists (select 1 from purchase_orders po where po.campaign_id = campaigns.id and po.status = 'paid' and po.receipt_status != 'received') then 2
            when status = 'proposta_selecionada' and exists (select 1 from proposals p where p.campaign_id = campaigns.id and p.status = 'aceite' and p.minimum_quantity is not null and p.reserved_until is not null) then 3
            when status = 'rascunho' then 4 else 7 end", [now(), now(), now()])->orderBy('payment_deadline')->orderBy('deadline')->orderByDesc('id');
    }

    /** @return array<string,mixed> */
    public function index(Request $request): array
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'phase' => ['nullable', Rule::in(['draft', 'interest', 'proposals', 'payment', 'supply', 'completed', 'cancelled'])], 'sort' => ['nullable', Rule::in(['urgent', 'deadline', 'recent'])], 'page' => ['nullable', 'integer', 'min:1'], 'page_attention' => ['nullable', 'integer', 'min:1'], 'page_waiting' => ['nullable', 'integer', 'min:1'], 'page_drafts' => ['nullable', 'integer', 'min:1'], 'page_closed' => ['nullable', 'integer', 'min:1']]);
        $search = trim((string) ($filters['search'] ?? ''));
        $phase = (string) ($filters['phase'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'urgent');
        $query = $this->query($request->user());
        if ($search !== '') {
            $reference = preg_match('/^(?:JNT-)?0*([1-9][0-9]*)$/i', $search, $matches) ? (int) $matches[1] : null;
            $query->where(fn (Builder $match) => $match->where('title', 'like', '%'.$search.'%')->orWhere('location', 'like', '%'.$search.'%')->when($reference, fn (Builder $id) => $id->orWhereKey($reference)));
        }
        if ($phase !== '') {
            match ($phase) {
                'draft' => $query->where('purchase_phase', 'interest')->where('status', 'rascunho'),
                'interest' => $query->where('purchase_phase', 'interest')->where('status', 'ativa'),
                'proposals' => $query->where('purchase_phase', 'interest')->whereIn('status', ['em_avaliacao', 'proposta_selecionada']),
                'payment' => $query->whereIn('purchase_phase', ['payment', 'review']),
                'supply' => $query->where('purchase_phase', 'supply'),
                'completed' => $query->where(fn (Builder $q) => $q->where('purchase_phase', 'completed')->orWhere('status', 'concluida')),
                'cancelled' => $query->where(fn (Builder $q) => $q->where('purchase_phase', 'cancelled')->orWhere('status', 'cancelada')),
                default => null,
            };
        }
        $sections = $this->listSections($request, clone $query);
        match ($sort) {
            'recent' => $query->latest('created_at')->latest('id'), 'deadline' => $query->orderByRaw('coalesce(payment_deadline, deadline)')->orderByDesc('id'), default => $this->urgent($query)
        };
        $page = $query->paginate(20)->withQueryString();
        $page->setCollection(collect($this->decorate(new Collection($page->items()))));

        return ['organizerCampaigns' => $page, 'organizerCampaignSections' => $sections, 'organizerFilters' => compact('search', 'phase', 'sort')];
    }

    /** @param Builder<Campaign> $query
     * @return list<array<string,mixed>>
     */
    private function listSections(Request $request, Builder $query): array
    {
        $groups = ['attention' => [], 'waiting' => [], 'drafts' => [], 'closed' => []];
        (clone $query)->reorder()->chunkById(100, function (Collection $models) use (&$groups): void {
            foreach ($this->decorate($models) as $view) {
                $groups[$view['list']['section']][] = ['id' => $view['campaign']['id'], 'priority' => $view['list']['priorityAt'] ?? PHP_INT_MAX];
            }
        });
        $labels = ['attention' => 'Precisam de si', 'waiting' => 'A aguardar', 'drafts' => 'Rascunhos', 'closed' => 'Concluídas e canceladas'];
        $sections = [];
        foreach ($groups as $key => $items) {
            if ($items === []) {
                continue;
            }
            usort($items, fn (array $left, array $right): int => ($left['priority'] <=> $right['priority']) ?: ($right['id'] <=> $left['id']));
            $current = min($request->integer('page_'.$key, 1), (int) max(1, ceil(count($items) / 20)));
            $ids = array_column(array_slice($items, ($current - 1) * 20, 20), 'id');
            $views = [];
            foreach ($this->decorate((clone $query)->reorder()->whereKey($ids)->get()) as $view) {
                $views[$view['campaign']['id']] = $view;
            }
            $rows = array_values(array_filter(array_map(fn (int $id): ?array => $views[$id] ?? null, $ids)));
            $page = new LengthAwarePaginator($rows, count($items), 20, $current, ['path' => $request->url(), 'pageName' => 'page_'.$key]);
            $page->appends($request->except(['page_'.$key, 'page', 'sort']));
            $sections[] = ['key' => $key, 'label' => $labels[$key], 'collapsed' => in_array($key, ['drafts', 'closed'], true), 'campaigns' => $page];
        }

        return $sections;
    }

    /** @return array<string,mixed> */
    public function dashboard(User $user): array
    {
        $query = $this->query($user);
        $tasks = [];
        $campaigns = [];
        $this->urgent(clone $query)->chunk(20, function (Collection $models) use (&$tasks, &$campaigns): bool {
            foreach ($this->decorate($models) as $row) {
                if (count($campaigns) < 5) {
                    $campaigns[] = $row;
                }
                if ($row['state']['attention'] && count($tasks) < 8) {
                    $tasks[] = $row;
                }
            }

            return count($tasks) < 8;
        });
        $active = (clone $query)->whereNotIn('purchase_phase', ['completed', 'cancelled'])->whereNotIn('status', ['rascunho', 'concluida', 'cancelada'])->count();
        $awaiting = (clone $query)->where('purchase_phase', 'interest')->whereIn('status', ['ativa', 'em_avaliacao'])->whereDoesntHave('proposals', fn (Builder $q) => $q->whereIn('status', ['submetida', 'em_avaliacao'])->where('valid_until', '>', now()))->count();
        $payments = (clone $query)->whereIn('purchase_phase', ['payment', 'review'])->count();
        $deliveries = PurchaseOrder::query()->whereIn('campaign_id', (clone $query)->where('purchase_phase', 'supply')->select('campaigns.id'))->where('status', 'paid')->where('receipt_status', '!=', 'received')->count();

        return ['inbox' => $this->inbox($user), 'tasks' => $tasks, 'campaigns' => $campaigns,
            'metrics' => ['active' => $active, 'awaitingProposals' => $awaiting, 'payments' => $payments, 'deliveries' => $deliveries]];
    }

    /** @return array<string,mixed> */
    private function inbox(User $user): array
    {
        $tasks = [];
        $waiting = [];
        $priority = static fn (array $left, array $right): int => (($left['list']['priorityAt'] ?? PHP_INT_MAX) <=> ($right['list']['priorityAt'] ?? PHP_INT_MAX)) ?: ($right['campaign']['id'] <=> $left['campaign']['id']);
        $this->query($user)->reorder()->chunkById(100, function (Collection $models) use (&$tasks, &$waiting, $priority): void {
            foreach ($this->decorate($models) as $view) {
                if ($view['list']['section'] === 'attention') {
                    $tasks[] = $view;
                } elseif ($view['list']['section'] === 'waiting') {
                    $waiting[] = $view;
                    usort($waiting, $priority);
                    $waiting = array_slice($waiting, 0, 5);
                }
            }
        });
        usort($tasks, $priority);

        return ['tasks' => $tasks, 'waiting' => $waiting, 'count' => count($tasks)];
    }

    /** @return array<string,mixed> */
    public function summary(Request $request, Campaign $campaign): array
    {
        $view = $this->campaign($request->user(), $campaign);
        $view['forecastVersion'] = $campaign->effectiveStage() === 'interest' ? $this->forecast->version($campaign) : null;
        $filters = $request->validate(['receipt' => ['nullable', Rule::in(['waiting', 'ready', 'received'])]]);
        $receipt = $campaign->purchase_phase === 'supply' ? ($filters['receipt'] ?? null) : null;
        $operations = $this->progress->operations($campaign);
        $orders = PurchaseOrder::query()->where('campaign_id', $campaign->id)->with(['campaign', 'user', 'attempts.refund', 'issues'])
            ->when($campaign->purchase_phase === 'supply', fn (Builder $query) => $query->where('status', 'paid'))
            ->when($receipt, fn (Builder $query) => $query->where('receipt_status', $receipt))->orderBy('id')->paginate(20)->withQueryString()
            ->through(fn (PurchaseOrder $order): array => [...$this->purchases->order($order), 'state' => $this->progress->visibleOrder($order, $operations['unallocatedQuantity']), 'canPrepare' => $campaign->purchase_phase === 'supply' && $order->status === 'paid' && $order->receipt_status === 'waiting' && $order->quantity <= $operations['unallocatedQuantity']]);

        return ['organizerCampaign' => $view, 'organizerHistory' => $this->history($campaign), 'purchase' => $this->purchases->campaign($campaign), 'operations' => $operations, 'orders' => $orders, 'orderFilter' => $receipt ?? '', 'participants' => $this->participants($campaign, $operations['unallocatedQuantity']), 'campaignDetail' => $view['campaign']];
    }

    /** @return array<string,mixed> */
    public function campaign(User $user, Campaign $campaign): array
    {
        $model = $this->query($user)->whereKey($campaign->id)->firstOrFail();

        return $this->decorate(new Collection([$model]))[0];
    }

    /** @return list<array<string,mixed>> */
    public function all(User $user): array
    {
        return $this->decorate($this->query($user)->latest('id')->get());
    }

    /** @param Collection<int,Campaign> $models
     * @return list<array<string,mixed>>
     */
    private function decorate(Collection $models): array
    {
        if ($models->isEmpty()) {
            return [];
        }
        $ids = $models->modelKeys();
        $orders = PurchaseOrder::query()->whereIn('campaign_id', $ids)->select('campaign_id')->selectRaw("count(*) as order_count,
            coalesce(sum(case when status = 'paid' then quantity else 0 end),0) as paid_units,
            coalesce(sum(case when status in ('paid','refunded','refund_pending') then quantity else 0 end),0) as charged_units,
            sum(case when status = 'paid' and receipt_status = 'ready' and not exists (select 1 from purchase_issues pi where pi.purchase_order_id = purchase_orders.id and pi.status = 'open') then 1 else 0 end) as ready_orders,
            sum(case when status = 'paid' and receipt_status = 'waiting' and not exists (select 1 from purchase_issues pi where pi.purchase_order_id = purchase_orders.id and pi.status = 'open') then 1 else 0 end) as waiting_orders,
            sum(case when receipt_status = 'received' then 1 else 0 end) as received_orders,
            sum(case when status = 'refunded' then 1 else 0 end) as refunded_orders,
            coalesce(sum(case when receipt_status in ('ready','received') then quantity else 0 end),0) as allocated_units,
            coalesce(min(case when status = 'paid' and receipt_status = 'waiting' and not exists (select 1 from purchase_issues pi where pi.purchase_order_id = purchase_orders.id and pi.status = 'open') then quantity end),0) as minimum_waiting_quantity, sum(case when exists (select 1 from purchase_issues pi where pi.purchase_order_id = purchase_orders.id and pi.status = 'open') then 1 else 0 end) as open_issues")
            ->groupBy('campaign_id')->get()->keyBy('campaign_id');
        $payments = PaymentAttempt::query()->join('purchase_orders', 'purchase_orders.id', '=', 'payment_attempts.purchase_order_id')
            ->whereIn('purchase_orders.campaign_id', $ids)->whereIn('payment_attempts.status', ['pending', 'review'])
            ->select('purchase_orders.campaign_id')->selectRaw('count(*) as pending_payments, sum(case when escalated_at is not null then 1 else 0 end) as escalated_payments')->groupBy('purchase_orders.campaign_id')->get()->keyBy('campaign_id');
        $supplies = SupplyOrder::query()->whereIn('campaign_id', $ids)->get()->keyBy('campaign_id');
        $finance = FinancialEntry::query()->whereIn('campaign_id', $ids)->select('campaign_id')->selectRaw("coalesce(sum(case when kind = 'payment_received' then amount_cents else 0 end),0) as received_cents, coalesce(sum(case when kind = 'refund_paid' then amount_cents else 0 end),0) as refunded_cents, coalesce(sum(case when kind = 'supplier_due' then amount_cents else 0 end),0) as supplier_cents")->groupBy('campaign_id')->get()->keyBy('campaign_id');

        return array_values($models->map(function (Campaign $campaign) use ($orders, $payments, $supplies, $finance): array {
            $order = $orders->get($campaign->id);
            $payment = $payments->get($campaign->id);
            $supply = $supplies->get($campaign->id);
            $money = $finance->get($campaign->id);
            $metrics = ['openIssues' => (int) ($order?->getAttribute('open_issues') ?? 0), 'participants' => (int) $campaign->participant_count, 'interestUnits' => (int) $campaign->current_quantity,
                'proposals' => (int) $campaign->getAttribute('proposals_count'), 'openProposals' => (int) $campaign->getAttribute('open_proposals_count'),
                'paidUnits' => (int) ($order?->getAttribute('paid_units') ?? 0), 'chargedUnits' => (int) ($order?->getAttribute('charged_units') ?? 0),
                'readyOrders' => (int) ($order?->getAttribute('ready_orders') ?? 0), 'waitingOrders' => (int) ($order?->getAttribute('waiting_orders') ?? 0),
                'receivedOrders' => (int) ($order?->getAttribute('received_orders') ?? 0), 'refundedOrders' => (int) ($order?->getAttribute('refunded_orders') ?? 0),
                'allocatedUnits' => (int) ($order?->getAttribute('allocated_units') ?? 0), 'receivedUnits' => $supply->received_quantity ?? 0,
                'unallocatedUnits' => max(0, ($supply->received_quantity ?? 0) - (int) ($order?->getAttribute('allocated_units') ?? 0)),
                'minimumWaitingQuantity' => (int) ($order?->getAttribute('minimum_waiting_quantity') ?? 0),
                'pendingPayments' => (int) ($payment?->getAttribute('pending_payments') ?? 0), 'escalatedPayments' => (int) ($payment?->getAttribute('escalated_payments') ?? 0),
                'receivedCents' => (int) ($money?->getAttribute('received_cents') ?? 0), 'refundedCents' => (int) ($money?->getAttribute('refunded_cents') ?? 0), 'supplierCents' => (int) ($money?->getAttribute('supplier_cents') ?? 0)];
            $state = $this->progress->organizer($campaign, $metrics);
            $stage = $campaign->effectiveStage();
            $isPayment = in_array($stage, ['payment', 'review'], true);
            $target = $isPayment ? (int) ($campaign->purchase_config['minimum_quantity'] ?? 0) : $campaign->target_quantity;
            $value = $isPayment ? $metrics['paidUnits'] : $metrics['interestUnits'];
            if ($stage === 'supply') {
                $value = $metrics['receivedUnits'];
                $target = $metrics['chargedUnits'];
            }
            $offer = $campaign->proposals->firstWhere('status', 'aceite');
            $offerDeadline = $offer?->reserved_until !== null && $offer->reserved_until->lessThan($offer->valid_until) ? $offer->reserved_until : $offer?->valid_until;
            $deadline = $state['isFinal'] ? null : ($isPayment ? $campaign->payment_deadline : ($stage === 'interest' ? $campaign->deadline : (in_array($stage, ['selection', 'confirmed'], true) ? $offerDeadline : null)));

            $list = $this->progress->organizerList($campaign, $state, $metrics, $deadline?->toIso8601String());

            return ['hasPurchase' => $campaign->purchase_config !== null, 'purchaseMinimum' => (int) ($campaign->purchase_config['minimum_quantity'] ?? 0), 'canRemove' => ! (bool) $campaign->getAttribute('participations_exists') && ! (bool) $campaign->getAttribute('proposals_exists'), 'campaign' => $this->platform->campaign($campaign), 'state' => $state, 'metrics' => $metrics, 'workspace' => $this->progress->organizerWorkspace($campaign, $state, $list, $metrics),
                'list' => $list,
                'progress' => ['value' => $value, 'target' => $target, 'label' => $isPayment ? 'Pagos '.$value.' de '.$target.' do mínimo' : $value.' de '.$target.' '.$campaign->unit.($stage === 'supply' ? ' recebidas' : '')],
                'deadline' => $this->deadline($deadline?->toIso8601String()), 'canEdit' => $campaign->canEditConditions(),
                'editBlockedReason' => $campaign->canEditConditions() ? null : 'As condições ficam protegidas depois da primeira participação ou proposta. Pode continuar a gerir a campanha.',
                'canCancel' => $campaign->purchase_phase === 'interest' && ! $campaign->hasFixedPurchaseTerms() && in_array($campaign->status, ['rascunho', 'ativa', 'em_avaliacao', 'proposta_selecionada'], true) && (int) ($order?->getAttribute('order_count') ?? 0) === 0,
                'supplierName' => $offer?->supplier->company_name,
                'deliveryLabel' => ['recolha' => 'Recolha', 'entrega' => 'Entrega', 'ambos' => 'Recolha ou entrega'][$campaign->delivery_mode] ?? $campaign->delivery_mode];
        })->all());
    }

    /** @return array{value:string,label:string,detail:string,isPast:bool}|null */
    public function deadline(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $date = CarbonImmutable::parse($value)->setTimezone('Africa/Maputo');
        $now = CarbonImmutable::now('Africa/Maputo');
        $past = $date->lessThanOrEqualTo($now);
        $label = $past ? 'Prazo terminado' : ($date->isSameDay($now) ? 'Termina hoje às '.$date->format('H:i') : ($date->isSameDay($now->addDay()) ? 'Termina amanhã às '.$date->format('H:i') : 'Faltam '.max(1, (int) ceil($now->diffInDays($date))).' dias'));

        return ['value' => $value, 'label' => $label, 'detail' => $date->format('d/m/Y').' às '.$date->format('H:i').' · Maputo', 'isPast' => $past];
    }

    /** @return list<array<string,mixed>> */
    public function participants(Campaign $campaign, int $available): array
    {
        $orders = PurchaseOrder::query()->where('campaign_id', $campaign->id)->with(['campaign', 'user', 'attempts.refund', 'issues'])->get()->keyBy('participation_id');

        return array_values($campaign->participations()->with('user')->orderBy('id')->get()->map(function (Participation $participation) use ($orders, $available, $campaign): array {
            $order = $orders->get($participation->id);
            $state = $this->progress->visibleParticipation($participation, $campaign, $order, $available);

            return ['id' => $participation->id, 'name' => $participation->user->name, 'phone' => (string) ($participation->user->contact_preferences['phone'] ?? ''), 'quantity' => $order->quantity ?? $participation->quantity, 'joinedAt' => $participation->created_at->toIso8601String(), 'notes' => $participation->notes ?? '', 'state' => $state, 'orderId' => $order?->id, 'amountCents' => $order?->total_cents];
        })->all());
    }

    /** @return list<array<string,mixed>> */
    public function offers(Campaign $campaign): array
    {
        $quantity = (int) $campaign->participations()->where('status', '!=', 'cancelada')->sum('quantity');
        $offers = $campaign->proposals()->with('supplier')->latest('id')->get();
        $eligible = $offers->filter(fn (Proposal $offer): bool => $campaign->status === 'em_avaliacao' && $campaign->purchase_phase === 'interest' && $campaign->purchase_config === null && in_array($offer->status, ['submetida', 'em_avaliacao'], true) && $offer->valid_until->isFuture() && $offer->supplier->verified && ProposalValidation::conditionsBlocker(ProposalValidation::conditions($offer)) === null);
        $best = $eligible->min(fn (Proposal $offer): int => Money::cents($offer->unit_price));
        $fastest = $eligible->min('delivery_days');

        return array_values($offers->map(function (Proposal $offer) use ($eligible, $best, $fastest, $quantity): array {
            $unit = Money::cents($offer->unit_price);
            $canSelect = $eligible->contains('id', $offer->id);
            $selected = $offer->status === 'aceite';
            $labels = ['submetida' => 'Recebida', 'em_avaliacao' => 'Em análise', 'aceite' => 'Escolhida', 'recusada' => 'Não escolhida', 'expirada' => 'Prazo terminado', 'retirada' => 'Retirada', 'rascunho' => 'Em preparação'];

            return ['id' => $offer->id, 'supplierName' => $offer->supplier->company_name, 'unitCents' => $unit, 'totalCents' => $quantity > 0 && $unit <= intdiv(Money::MAX_CENTS, $quantity) ? Money::multiply($unit, $quantity) : null,
                'quantity' => $offer->available_quantity, 'minimum' => $offer->minimum_quantity, 'deliveryDays' => $offer->delivery_days, 'transportCents' => $offer->transport_unit_cents, 'transportIncluded' => $offer->transport_included, 'deliveryLabel' => ['recolha' => 'Recolha', 'entrega' => 'Entrega', 'ambos' => 'Recolha ou entrega'][$offer->delivery_mode] ?? $offer->delivery_mode,
                'validity' => $this->deadline($offer->valid_until->toIso8601String()), 'notes' => $offer->notes ?? '', 'coverage' => $quantity > 0 ? ($offer->available_quantity >= $quantity ? 'Cobre a procura' : 'Cobre parte da procura') : 'Sem procura registada',
                'canSelect' => $canSelect, 'blockedReason' => $canSelect || $selected ? null : (ProposalValidation::conditionsBlocker(ProposalValidation::conditions($offer)) ?? (! $offer->supplier->verified ? 'A administração precisa de verificar este fornecedor.' : (! $offer->valid_until->isFuture() ? 'A validade desta proposta terminou.' : 'Esta proposta já não pode ser escolhida nesta etapa.'))),
                'bestPrice' => $canSelect && $unit === $best, 'fastest' => $canSelect && $offer->delivery_days === $fastest, 'selected' => $selected,
                'state' => ['code' => $offer->status, 'label' => $labels[$offer->status] ?? 'Recebida', 'tone' => $selected ? 'success' : ($canSelect ? 'info' : 'neutral'), 'icon' => $selected ? 'check' : 'document', 'isFinal' => in_array($offer->status, ['aceite', 'recusada', 'retirada', 'expirada'], true), 'primaryAction' => null, 'waitingOn' => null, 'blocker' => null]];
        })->all());
    }

    /** @return list<array<string,mixed>> */
    public function history(Campaign $campaign): array
    {
        $labels = ['campaign.created' => 'Campanha criada', 'campaign.updated' => 'Condições actualizadas', 'campaign.published' => 'Campanha publicada', 'campaign.cancelled' => 'Campanha cancelada', 'purchase.completed' => 'Campanha concluída', 'proposal.submitted' => 'Proposta recebida', 'proposal.selected' => 'Fornecedor escolhido', 'supplier.conditions_confirmed' => 'Fornecedor confirmou as condições', 'payments.opened' => 'Pagamentos abertos', 'interest.closed' => 'Interesse encerrado', 'interest.closed.automatically' => 'Prazo de interesse terminado', 'proposal.updated' => 'Proposta actualizada', 'proposal.expired' => 'Proposta expirada'];

        return array_values(AuditEntry::query()->where(fn (Builder $q) => $q->where(fn (Builder $record) => $record->where('subject_type', 'Campaign')->where('subject_id', $campaign->id))->orWhere(fn (Builder $record) => $record->where('subject_type', 'Proposal')->whereIn('subject_id', $campaign->proposals()->select('id'))))
            ->latest('id')->limit(30)->get()->map(fn (AuditEntry $entry): array => ['id' => $entry->id, 'label' => $labels[$entry->action] ?? 'Campanha actualizada', 'actor' => $entry->actor_name, 'createdAt' => $entry->created_at->toIso8601String()])->all());
    }
}
