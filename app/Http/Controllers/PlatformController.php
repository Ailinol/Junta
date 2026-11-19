<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\Participation;
use App\Support\AdminData;
use App\Support\OrganizerData;
use App\Support\PurchaseData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    public function __invoke(Request $request, PurchaseData $purchases, OrganizerData $organizer): Response|RedirectResponse
    {
        $extra = [];
        if ($request->routeIs('admin.campanhas.index')) {
            $extra['adminCampaigns'] = app(AdminData::class)->campaigns($request->user());
        }
        if ($request->routeIs('consumidor.dashboard', 'consumidor.campanhas.index', 'consumidor.historico')) {
            $extra['consumerOrders'] = $purchases->consumerOrders($request->user());
        }

        if ($request->routeIs('organizador.dashboard')) {
            $extra['organizerDashboard'] = $organizer->dashboard($request->user());
        }
        if ($request->routeIs('organizador.campanhas.index')) {
            $extra = [...$extra, ...$organizer->index($request)];
        }
        if ($request->routeIs('campanhas.show')) {
            Campaign::query()->whereIn('status', Campaign::PUBLIC_STATUSES)->findOrFail($request->route('id'));
        }
        if ($request->routeIs('consumidor.acompanhamento', 'consumidor.acompanhamento.show', 'consumidor.campanhas.show')) {
            $participation = Participation::query()->where('user_id', $request->user()->id)
                ->when($request->route('id'), fn ($q, $id) => $q->where('campaign_id', $id))->latest('id')->first();
            if ($request->routeIs('consumidor.acompanhamento') && $participation) {
                return to_route('consumidor.acompanhamento.show', ['id' => $participation->campaign_id]);
            }
            $extra['purchase'] = $participation ? $purchases->consumer($participation->campaign, $request->user()) : null;
        }
        if ($request->routeIs('organizador.campanhas.show', 'organizador.campanhas.participantes', 'organizador.campanhas.propostas')) {
            $campaign = Campaign::query()->whereKey($request->route('id'))->firstOrFail();
            abort_unless(Gate::allows('manage-campaign', $campaign), 404);
            $view = $organizer->campaign($request->user(), $campaign);
            $extra['organizerCampaign'] = $view;
            $extra['organizerHistory'] = $organizer->history($campaign);
            if ($request->routeIs('organizador.campanhas.propostas')) {
                $extra['organizerOffers'] = $organizer->offers($campaign);
            }
            if ($request->routeIs('organizador.campanhas.show')) {
                $extra = [...$extra, ...$organizer->summary($request, $campaign)];
                $extra['campaignHistory'] = AuditEntry::query()->where(function (Builder $query) use ($campaign): void {
                    $query->where(fn (Builder $record) => $record->where('subject_type', 'Campaign')->where('subject_id', $campaign->id))
                        ->orWhere(fn (Builder $record) => $record->where('subject_type', 'Proposal')->whereIn('subject_id', $campaign->proposals()->select('id')));
                })->latest('id')->limit(30)->get()->map(fn (AuditEntry $entry): array => [
                    'id' => $entry->id, 'action' => $entry->action, 'actor' => $entry->actor_name, 'createdAt' => $entry->created_at->toIso8601String(),
                ])->all();
            }
            if ($request->routeIs('organizador.campanhas.participantes')) {
                $extra['participants'] = $organizer->participants($campaign, $view['metrics']['unallocatedUnits']);
            }
        }

        return Inertia::render($request->route('page'), $extra);
    }
}
