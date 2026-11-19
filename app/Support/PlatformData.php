<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\OrganizerApplication;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class PlatformData
{
    /** @return array<string,mixed> */
    public function forRequest(Request $request): array
    {
        $public = $request->routeIs('home', 'campanhas.*', 'publico.*');
        $user = $public ? null : $request->user();
        $admin = $user?->can('administer-platform') ?? false;
        $campaignQuery = Campaign::query()->with('organizer')
            ->withCount(['participations as participant_count' => fn (Builder $q) => $q->where('status', '!=', 'cancelada')])
            ->withSum(['participations as current_quantity' => fn (Builder $q) => $q->where('status', '!=', 'cancelada')], 'quantity');
        if ($request->routeIs('organizador.*')) {
            $campaignQuery->withExists(['participations', 'proposals']);
            $campaignQuery->with(['proposals' => fn (HasMany $proposals) => $proposals->where('status', 'aceite')]);
        }
        if ($public) {
            $campaignQuery->whereIn('status', Campaign::PUBLIC_STATUSES);
        } elseif (! $admin) {
            if ($request->routeIs('organizador.*')) {
                $campaignQuery->where('organizer_id', $user?->id);
            } else {
                $campaignQuery->where(function (Builder $q) use ($user): void {
                    $q->where('status', '!=', 'rascunho');
                    if ($user) {
                        $q->orWhere('organizer_id', $user->id);
                    }
                });
            }
        }
        if ($request->routeIs('organizador.dashboard', 'organizador.campanhas.index', 'organizador.campanhas.criar')) {
            $campaignQuery->whereRaw('1 = 0');
        }
        $campaignModels = $campaignQuery->orderByDesc('created_at')->orderByDesc('id')->get();
        $campaigns = $campaignModels->map(fn (Campaign $campaign): array => $this->campaign($campaign))->keyBy('id');
        $participations = $user ? Participation::query()->where('user_id', $user->id)->whereIn('campaign_id', $campaigns->keys())->latest('id')->get()->map(fn (Participation $p): array => [
            'id' => $p->id, 'campaignId' => $p->campaign_id, 'campaign' => $campaigns[$p->campaign_id],
            'quantity' => $p->quantity, 'status' => $p->status, 'joinedAt' => $p->created_at->toIso8601String(), 'notes' => $p->notes,
        ])->values()->all() : [];
        $supplier = $user?->supplier;
        $proposalQuery = Proposal::query()->with('supplier')->whereIn('campaign_id', $campaigns->keys());
        if ($request->routeIs('fornecedor.*')) {
            $proposalQuery->where('supplier_id', $supplier->id ?? 0);
        } elseif (! $admin) {
            $proposalQuery->where(function (Builder $q) use ($user, $supplier): void {
                if (! $user) {
                    $q->whereRaw('1 = 0');

                    return;
                }
                $q->whereHas('campaign', fn (Builder $campaign) => $campaign->where('organizer_id', $user->id));
                if ($supplier) {
                    $q->orWhere('supplier_id', $supplier->id);
                }
            });
        }
        $proposals = $proposalQuery->latest('id')->get()->map(fn (Proposal $p): array => [
            'id' => $p->id, 'campaignId' => $p->campaign_id, 'campaign' => $campaigns[$p->campaign_id],
            'supplierId' => $p->supplier_id, 'supplierName' => $p->supplier->company_name, 'supplierVerified' => $p->supplier->verified,
            'unitPrice' => (float) $p->unit_price, 'availableQuantity' => $p->available_quantity,
            'minimumQuantity' => $p->minimum_quantity, 'reservedUntil' => $p->reserved_until?->toIso8601String(),
            'transportIncluded' => $p->transport_included, 'transportUnitPrice' => $p->transport_unit_cents === null ? null : $p->transport_unit_cents / 100,
            'totalEstimated' => (float) $p->total_estimated, 'deliveryDays' => $p->delivery_days,
            'deliveryMode' => $p->delivery_mode, 'validUntil' => $p->valid_until->toIso8601String(),
            'status' => $p->status, 'notes' => $p->notes, 'submittedAt' => $p->submitted_at->toIso8601String(),
        ])->values()->all();
        $ownApplication = $user?->organizerApplication;
        $paymentOrders = $user ? PurchaseOrder::query()->where('user_id', $user->id)->pluck('id', 'campaign_id') : collect();
        $organizerNotifications = $request->routeIs('notifications.index') && $request->query('area') === 'organizador';
        $notifications = $user ? $user->notifications()->latest()->limit(100)->get()->map(fn ($n): array => [
            'id' => $n->id, 'title' => $n->data['title'], 'message' => ($request->routeIs('consumidor.*') || $organizerNotifications) ? str_ireplace(['Reembolso integral confirmado pelo prestador de teste.', 'prestador de teste', 'prestador', 'apuramento', 'tentativas', 'tentativa', 'reserva de capacidade', 'O fornecimento', 'fornecimento', 'conferência', 'ocorrências', 'ocorrência'], ['O valor total da compra foi devolvido.', 'sistema de pagamento', 'serviço de pagamento', 'verificação da compra', 'pagamentos', 'pagamento', 'disponibilidade', 'A mercadoria a caminho', 'mercadoria a caminho', 'verificação', 'problemas com pedidos', 'problema com o pedido'], $n->data['message']) : $n->data['message'], 'type' => $n->data['type'] ?? 'info',
            'read' => $n->read_at !== null, 'createdAt' => $n->created_at->toIso8601String(), 'link' => $organizerNotifications && str_contains($n->data['link'] ?? '', '/admin/') ? null : (($n->data['title'] ?? '') === 'Pague a sua compra' && preg_match('#^/consumidor/acompanhamento/([0-9]+)$#', $n->data['link'] ?? '', $matches) && $paymentOrders->has((int) $matches[1]) ? route('consumidor.pedidos.checkout', ['order' => $paymentOrders[(int) $matches[1]]], absolute: false) : ($n->data['link'] ?? null)),
        ])->all() : [];

        return [
            'isDemo' => false, 'campaigns' => $campaigns->values()->all(), 'participations' => $participations, 'proposals' => $proposals,
            'suppliers' => $admin ? Supplier::query()->orderBy('company_name')->get()->map(fn (Supplier $s): array => $this->supplier($s))->all() : [],
            'users' => $admin ? User::query()->orderBy('name')->get()->map(fn (User $u): array => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'role' => $u->is_admin ? 'admin' : ($u->is_organizer ? 'organizador' : 'consumidor'),
                'roles' => array_values(array_filter(['consumidor', $u->is_organizer ? 'organizador' : null, $u->is_admin ? 'admin' : null])),
                'createdAt' => $u->created_at->toIso8601String(),
            ])->all() : [],
            'notifications' => $notifications, 'consumerProfile' => array_replace(['phone' => '', 'city' => '', 'address' => '', 'notifProposal' => true, 'notifReminder' => true, 'notifPickup' => true], $user->contact_preferences ?? []),
            'supplierProfile' => $supplier ? $this->supplier($supplier) : ['id' => 0, 'companyName' => '', 'nuit' => '', 'contactName' => '', 'email' => '', 'phone' => '', 'location' => '', 'categories' => [], 'verified' => false, 'description' => ''],
            'organizerApplication' => $ownApplication ? $this->application($ownApplication) : null,
            'applications' => $admin ? OrganizerApplication::query()->with(['user', 'reviewer'])->latest('id')->get()->map(fn (OrganizerApplication $a): array => $this->application($a))->all() : [],
            'audit' => $admin ? AuditEntry::query()->latest('id')->limit(100)->get()->map(fn (AuditEntry $entry): array => [
                'id' => $entry->id, 'actor' => $entry->actor_name, 'action' => $entry->action, 'subject' => $entry->subject_type,
                'subjectId' => $entry->subject_id, 'changes' => $entry->changes, 'createdAt' => $entry->created_at->toIso8601String(),
            ])->all() : [],
        ];
    }

    /** @return array<string,mixed> */
    public function campaign(Campaign $c): array
    {
        $data = ['id' => $c->id, 'reference' => sprintf('JNT-%06d', $c->id), 'title' => $c->title, 'description' => $c->description, 'category' => $c->category, 'status' => $c->status,
            'organizerId' => $c->organizer_id, 'organizerName' => $c->organizer->name ?? $c->organizer_name,
            'purchasePhase' => $c->purchase_phase, 'effectiveStage' => $c->effectiveStage(), 'paymentDeadline' => $c->payment_deadline?->toIso8601String(), 'acceptsProposals' => $c->canReceiveProposals(),
            'targetQuantity' => (int) $c->target_quantity, 'currentQuantity' => (int) ($c->current_quantity ?? 0), 'participantCount' => (int) ($c->participant_count ?? 0),
            'unit' => $c->unit, 'deadline' => $c->deadline->toIso8601String(), 'location' => $c->location, 'deliveryMode' => $c->delivery_mode,
            'createdAt' => $c->created_at->toIso8601String(), 'updatedAt' => $c->updated_at->toIso8601String(), 'tags' => $c->tags ?? [], 'isDemo' => false];
        if ($c->estimated_price !== null) {
            $data['estimatedPrice'] = (float) $c->estimated_price;
        }
        if ($c->image_url) {
            $data['imageUrl'] = $c->image_url;
        }
        if ($c->hasAttribute('participations_exists')) {
            $data['canEdit'] = $c->canEditConditions();
        }

        return $data;
    }

    /** @return array<string,mixed> */
    public function supplier(Supplier $s): array
    {
        return ['id' => $s->id, 'userId' => $s->user_id, 'companyName' => $s->company_name, 'nuit' => $s->nuit, 'contactName' => $s->contact_name,
            'email' => $s->email, 'phone' => $s->phone, 'location' => $s->location, 'categories' => $s->categories, 'description' => $s->description, 'verified' => $s->verified];
    }

    /** @return array<string,mixed> */
    public function application(OrganizerApplication $a): array
    {
        return ['id' => $a->id, 'userId' => $a->user_id, 'fullName' => $a->full_name, 'email' => $a->user->email,
            'phone' => $a->phone, 'location' => $a->location, 'motivation' => $a->motivation, 'status' => $a->status,
            'decisionReason' => $a->decision_reason, 'reviewerName' => $a->reviewer?->name, 'reviewedAt' => $a->reviewed_at?->toIso8601String(),
            'createdAt' => $a->created_at->toIso8601String()];
    }
}
