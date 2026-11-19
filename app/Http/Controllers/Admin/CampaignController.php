<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use App\Support\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function store(CampaignRequest $request): RedirectResponse
    {
        if (! in_array($request->validated('status'), ['rascunho', 'ativa'], true)) {
            throw ValidationException::withMessages(['status' => 'Crie a campanha antes de seleccionar uma proposta ou iniciar a execução.']);
        }
        DB::transaction(function () use ($request): void {
            $campaign = Campaign::create($request->campaignData());
            PlatformAudit::record($request->user(), 'campaign.created', $campaign);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campanha criada.']);

        return back();
    }

    public function update(CampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request, $campaign): void {
            Campaign::query()->whereKey($campaign->id)->update(['held_quantity' => DB::raw('held_quantity')]);
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $status = $request->validated('status');
            if ($status !== $locked->status && ! ($locked->purchase_phase === 'interest' && $locked->status === 'rascunho' && $status === 'ativa')) {
                throw ValidationException::withMessages(['status' => 'Use a operação de fecho, selecção ou cancelamento da campanha para mudar de etapa, indicando o motivo quando necessário.']);
            }
            if ($locked->hasFixedPurchaseTerms() || ($status !== $locked->status && in_array($status, ['proposta_selecionada', 'confirmada', 'em_fornecimento', 'concluida'], true))) {
                throw ValidationException::withMessages(['status' => 'Use o fluxo de compra para pagamentos, fornecimento e encerramento. As condições abertas estão fixadas.']);
            }
            if (in_array($status, ['proposta_selecionada', 'confirmada', 'em_fornecimento', 'concluida'], true) && ! $locked->proposals()->where('status', 'aceite')->exists()) {
                throw ValidationException::withMessages(['status' => 'Seleccione uma proposta através da comparação antes de avançar a execução.']);
            }
            if ($status === 'rascunho' && ($locked->participations()->exists() || $locked->proposals()->exists())) {
                throw ValidationException::withMessages(['status' => 'Uma campanha com participações ou propostas não pode voltar a rascunho.']);
            }
            if (! $locked->canEditConditions()) {
                throw ValidationException::withMessages(['campaign' => 'As condições ficam protegidas após a primeira participação ou proposta. Use as operações da campanha para avançar o processo.']);
            }
            $before = $locked->toArray();
            $locked->update($request->campaignData());
            PlatformAudit::record($request->user(), 'campaign.updated', $locked, ['before' => $before, 'after' => $locked->toArray()]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campanha actualizada.']);

        return back();
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request, $campaign): void {
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($locked->participations()->exists() || $locked->proposals()->exists()) {
                throw ValidationException::withMessages(['record' => 'Esta campanha tem participações ou propostas. Preserve o histórico e use o estado da campanha.']);
            } PlatformAudit::record($request->user(), 'campaign.deleted', $locked, ['title' => $locked->title]);
            $locked->delete();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campanha removida.']);

        return back();
    }
}
