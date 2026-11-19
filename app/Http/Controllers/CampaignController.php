<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use App\Models\PurchaseOrder;
use App\Support\OrganizerData;
use App\Support\PlatformAudit;
use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function cancel(Request $request, Campaign $campaign, PlatformNotifier $notifier): RedirectResponse
    {
        Gate::authorize('manage-campaign', $campaign);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $campaign, $data, $notifier): void {
            Campaign::query()->whereKey($campaign->id)->update(['held_quantity' => DB::raw('held_quantity')]);
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            Gate::authorize('manage-campaign', $locked);
            if ($locked->purchase_phase !== 'interest' || $locked->hasFixedPurchaseTerms() || ! in_array($locked->status, ['rascunho', 'ativa', 'em_avaliacao', 'proposta_selecionada'], true) || PurchaseOrder::query()->where('campaign_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['campaign' => 'Esta campanha já tem condições de compra ou pagamentos. A administração deve tratar o cancelamento e eventuais reembolsos.']);
            }
            $recipients = $locked->participations()->with('user')->where('status', '!=', 'cancelada')->get();
            $locked->participations()->where('status', '!=', 'cancelada')->update(['status' => 'cancelada']);
            $locked->proposals()->whereIn('status', ['rascunho', 'submetida', 'em_avaliacao'])->update(['status' => 'expirada']);
            $locked->status = 'cancelada';
            $locked->purchase_phase = 'cancelled';
            $locked->save();
            PlatformAudit::record($request->user(), 'campaign.cancelled', $locked, ['reason' => $data['reason']]);
            foreach ($recipients as $participation) {
                if ($participation->user) {
                    $notifier->send($participation->user, 'campaign.cancelled:'.$locked->id, 'Campanha cancelada', $locked->title.'. Motivo: '.$data['reason'], route('consumidor.acompanhamento.show', ['id' => $locked->id], absolute: false));
                }
            }
            foreach ($locked->proposals()->with('supplier.user')->get() as $proposal) {
                if ($proposal->supplier->user) {
                    $notifier->send($proposal->supplier->user, 'campaign.cancelled:'.$locked->id, 'Campanha cancelada', $locked->title.'. Motivo: '.$data['reason'], route('fornecedor.propostas.index', absolute: false));
                }
            }
        }, 5);

        return to_route('organizador.campanhas.show', ['id' => $campaign->id]);
    }

    public function edit(Request $request, Campaign $campaign, OrganizerData $organizer): Response|RedirectResponse
    {
        abort_unless(Gate::allows('manage-campaign', $campaign), 404);
        try {
            $this->ensureEditable($campaign);
        } catch (ValidationException $exception) {
            return to_route('organizador.campanhas.show', ['id' => $campaign->id])->withErrors($exception->errors());
        }

        return Inertia::render('organizador/campanhas/criar', ['editingCampaignId' => $campaign->id, 'organizerCampaign' => $organizer->campaign($request->user(), $campaign)]);
    }

    public function update(CampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request, $campaign): void {
            Campaign::query()->whereKey($campaign->id)->update(['held_quantity' => DB::raw('held_quantity')]);
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            Gate::authorize('manage-campaign', $locked);
            $this->ensureEditable($locked);
            $data = $request->campaignData();
            unset($data['organizer_id'], $data['organizer_name']);
            $before = $locked->toArray();
            $previousStatus = $locked->status;
            $locked->update($data);
            PlatformAudit::record($request->user(), $previousStatus === 'rascunho' && $locked->status === 'ativa' ? 'campaign.published' : 'campaign.updated', $locked, ['before' => $before, 'after' => $locked->toArray()]);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campanha actualizada.']);

        return to_route('organizador.campanhas.show', ['id' => $campaign->id]);
    }

    private function ensureEditable(Campaign $campaign): void
    {
        if (! $campaign->canEditConditions()) {
            throw ValidationException::withMessages(['campaign' => 'As condições ficam protegidas após a primeira participação ou proposta. Use a gestão da campanha para avançar o processo.']);
        }
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        if (! in_array($request->validated('status'), ['rascunho', 'ativa'], true)) {
            throw ValidationException::withMessages(['status' => 'Uma nova campanha deve começar como rascunho ou aberta à participação.']);
        }
        $campaign = DB::transaction(function () use ($request): Campaign {
            $c = Campaign::create($request->campaignData());
            PlatformAudit::record($request->user(), 'campaign.created', $c);

            return $c;
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campanha guardada.']);

        return to_route('organizador.campanhas.show', ['id' => $campaign->id]);
    }
}
