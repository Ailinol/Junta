<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\OrganizerApplication;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Authorized, read-only projections for administrative supervision. */
class AdminData
{
    public function __construct(private OrganizerData $organizer) {}

    /** @return array{interventions:int,applications:int,suppliers:int} */
    public function navigation(User $user): array
    {
        Gate::forUser($user)->authorize('administer-platform');

        return ['interventions' => $this->interventionQuery()->count(), 'applications' => OrganizerApplication::query()->where('status', 'pending')->count(), 'suppliers' => Supplier::query()->where('verified', false)->count()];
    }

    /** One cause per order; campaign balances and readiness are separate interventions. */
    private function interventionQuery(): Builder
    {
        $openIssue = "exists (select 1 from purchase_issues i where i.purchase_order_id = o.id and i.status = 'open')";
        $orders = DB::table('purchase_orders as o')->join('campaigns as c', 'c.id', '=', 'o.campaign_id')->join('users as u', 'u.id', '=', 'o.user_id')
            ->selectRaw("o.campaign_id, o.id as order_id, c.title as campaign_title, u.name as participant_name,
                case when o.status = 'refund_pending' then 'refund' when $openIssue then 'issue' else 'payment' end as kind,
                case when o.status = 'refund_pending' then o.updated_at when $openIssue then (select min(i.created_at) from purchase_issues i where i.purchase_order_id = o.id and i.status = 'open') else coalesce((select min(a.created_at) from payment_attempts a where a.purchase_order_id = o.id and a.status in ('pending','review')), o.payment_deadline) end as since")
            ->where(function (Builder $query): void {
                $query->where('o.status', 'refund_pending')->orWhereExists(fn (Builder $issues) => $issues->selectRaw('1')->from('purchase_issues as i')->whereColumn('i.purchase_order_id', 'o.id')->where('i.status', 'open'))
                    ->orWhereExists(fn (Builder $attempts) => $attempts->selectRaw('1')->from('payment_attempts as a')->whereColumn('a.purchase_order_id', 'o.id')->where('a.status', 'review'))
                    ->orWhere(fn (Builder $pending) => $pending->where('o.status', 'payment_pending')->where('o.payment_deadline', '<=', now()));
            });
        $balances = DB::table('financial_entries')->select('campaign_id')->selectRaw("sum(case when kind in ('supplier_due','carrier_due') then amount_cents when kind in ('supplier_settled','carrier_settled') then -amount_cents else 0 end) as balance,
            min(case when kind in ('supplier_due','carrier_due') then created_at else null end) as since")->groupBy('campaign_id');
        $campaigns = DB::table('campaigns as c')->leftJoinSub($balances, 'f', 'f.campaign_id', '=', 'c.id')
            ->selectRaw("c.id as campaign_id, null as order_id, c.title as campaign_title, null as participant_name,
                case when coalesce(f.balance,0) <> 0 then 'balance' else 'ready' end as kind,
                case when coalesce(f.balance,0) <> 0 then coalesce(f.since,c.updated_at) else c.updated_at end as since")
            ->where('c.purchase_phase', 'supply')->where(function (Builder $query): void {
                $query->whereRaw('coalesce(f.balance,0) <> 0')->orWhere(function (Builder $ready): void {
                    $ready->whereNotExists(fn (Builder $orders) => $orders->selectRaw('1')->from('purchase_orders as p')->whereColumn('p.campaign_id', 'c.id')->where(fn (Builder $pending) => $pending->where('p.status', 'refund_pending')->orWhere(fn (Builder $paid) => $paid->where('p.status', 'paid')->where('p.receipt_status', '!=', 'received'))))
                        ->whereNotExists(fn (Builder $issues) => $issues->selectRaw('1')->from('purchase_issues as i')->join('purchase_orders as p', 'p.id', '=', 'i.purchase_order_id')->whereColumn('p.campaign_id', 'c.id')->where('i.status', 'open'));
                });
            });

        return DB::query()->fromSub($orders->unionAll($campaigns), 'interventions');
    }

    /** @return LengthAwarePaginator<int,array<string,mixed>> */
    public function interventions(User $user): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('administer-platform');

        return $this->interventionQuery()->orderBy('since')->orderBy('campaign_id')->orderBy('order_id')->paginate(20, ['*'], 'attention_page')->withQueryString()->through(fn (\stdClass $row): array => $this->intervention($row));
    }

    /** @return array<string,mixed> */
    private function intervention(\stdClass $row): array
    {
        $labels = ['payment' => 'Pagamento por confirmar', 'refund' => 'Reembolso pendente', 'issue' => 'Problema por resolver', 'ready' => 'Pronta a concluir', 'balance' => 'Saldo por liquidar'];
        $kind = (string) $row->kind;
        $campaignId = (int) $row->campaign_id;
        $orderId = $row->order_id === null ? null : (int) $row->order_id;
        $query = ['campaignId' => $campaignId, 'tab' => match ($kind) {
            'balance' => 'movimentos', 'ready' => 'encerramento', default => 'pedidos'
        }];
        if ($orderId !== null) {
            $query['orderId'] = $orderId;
        }
        $label = match ($kind) {
            'payment', 'refund' => 'Verificar', 'issue' => 'Resolver', 'balance' => 'Registar', default => 'Concluir'
        };

        return ['id' => $kind.':'.($orderId ?? $campaignId), 'campaignId' => $campaignId, 'orderId' => $orderId, 'campaignTitle' => $row->campaign_title, 'participantName' => $row->participant_name,
            'kind' => $kind, 'label' => $labels[$kind], 'since' => CarbonImmutable::parse($row->since)->toIso8601String(), 'primaryAction' => ['label' => $label, 'href' => route('admin.compras.index', $query, absolute: false)]];
    }

    /** @return array<string,mixed> */
    public function dashboard(Request $request): array
    {
        Gate::forUser($request->user())->authorize('administer-platform');
        $tasks = $this->interventionQuery()->get()->map(function (\stdClass $row): array {
            $intervention = $this->intervention($row);

            return ['id' => $intervention['id'], 'title' => $intervention['campaignTitle'], 'description' => $intervention['label'].($intervention['participantName'] ? ' · '.$intervention['participantName'] : ''), 'since' => $intervention['since'], 'primaryAction' => $intervention['primaryAction']];
        })->all();
        foreach (OrganizerApplication::query()->where('status', 'pending')->get(['id', 'full_name', 'created_at']) as $application) {
            $tasks[] = ['id' => 'application:'.$application->id, 'title' => $application->full_name, 'description' => 'Candidatura a organizador', 'since' => $application->created_at->toIso8601String(), 'primaryAction' => ['label' => 'Decidir', 'href' => route('admin.candidaturas.index', ['applicationId' => $application->id], absolute: false)]];
        }
        foreach (Supplier::query()->where('verified', false)->get(['id', 'company_name', 'created_at']) as $supplier) {
            $tasks[] = ['id' => 'supplier:'.$supplier->id, 'title' => $supplier->company_name, 'description' => 'Fornecedor por verificar', 'since' => $supplier->created_at->toIso8601String(), 'primaryAction' => ['label' => 'Verificar', 'href' => route('admin.fornecedores.index', ['supplierId' => $supplier->id], absolute: false)]];
        }
        usort($tasks, fn (array $left, array $right): int => strcmp($left['since'], $right['since']) ?: strcmp($left['id'], $right['id']));
        $page = max(1, $request->integer('tasks_page', 1));
        $pending = new LengthAwarePaginator(array_slice($tasks, ($page - 1) * 20, 20), count($tasks), 20, $page, ['path' => $request->url(), 'pageName' => 'tasks_page']);
        $pending->withQueryString();

        return ['tasks' => $pending, 'activity' => AuditEntry::query()->latest('id')->limit(8)->get()->map(fn (AuditEntry $entry): array => $this->audit($entry))->all()];
    }

    /** @return array<string,mixed> */
    public function history(Request $request): array
    {
        Gate::forUser($request->user())->authorize('administer-platform');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'action' => ['nullable', 'string', 'max:120'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from') ? 'after_or_equal:from' : null])]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query = AuditEntry::query()->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('actor_name', 'like', '%'.$search.'%')->orWhere('action', 'like', '%'.$search.'%')->orWhere('subject_type', 'like', '%'.$search.'%')))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action));
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], 'Africa/Maputo')->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'], 'Africa/Maputo')->addDay()->startOfDay()->utc());
        }

        return ['records' => $query->latest('id')->paginate(20)->withQueryString()->through(fn (AuditEntry $entry): array => $this->audit($entry)), 'filters' => ['search' => $search, 'action' => $filters['action'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''], 'actions' => AuditEntry::query()->distinct()->orderBy('action')->pluck('action')->all()];
    }

    /** @return array<string,mixed> */
    private function audit(AuditEntry $entry): array
    {
        return ['id' => $entry->id, 'action' => $entry->action, 'actor' => $entry->actor_name, 'subject' => $entry->subject_type, 'subjectId' => $entry->subject_id, 'createdAt' => $entry->created_at->toIso8601String(), 'changes' => $entry->changes ?? []];
    }

    /** @return list<array<string,mixed>> */
    public function campaigns(User $user): array
    {
        Gate::forUser($user)->authorize('administer-platform');

        return $this->organizer->all($user);
    }
}
