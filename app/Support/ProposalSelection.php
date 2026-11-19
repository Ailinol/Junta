<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProposalSelection
{
    public function __construct(private PlatformNotifier $notifier) {}

    public function select(User $actor, Campaign $campaign, Proposal $proposal): void
    {
        Gate::forUser($actor)->authorize('manage-campaign', $campaign);
        abort_unless($proposal->campaign_id === $campaign->id, 404);
        DB::transaction(function () use ($actor, $campaign, $proposal): void {
            Campaign::query()->whereKey($campaign->id)->update(['held_quantity' => DB::raw('held_quantity')]);
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            Gate::forUser($actor)->authorize('manage-campaign', $locked);
            $offer = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($offer->supplier_id);
            if ($offer->campaign_id !== $locked->id || $locked->status !== 'em_avaliacao' || $locked->purchase_phase !== 'interest' || $locked->purchase_config !== null || ! in_array($offer->status, ['submetida', 'em_avaliacao'], true) || ! $offer->valid_until->isFuture() || ! $supplier->verified || $locked->proposals()->where('status', 'aceite')->exists()) {
                throw ValidationException::withMessages(['proposal' => 'A campanha ou proposta já não permite esta selecção.']);
            }
            $conditions = ProposalValidation::conditions($offer);
            if (($message = ProposalValidation::conditionsBlocker($conditions)) !== null) {
                throw ValidationException::withMessages(['proposal' => $message]);
            }
            $locked->proposals()->whereKeyNot($offer->id)->whereIn('status', ['rascunho', 'submetida', 'em_avaliacao'])->update(['status' => 'recusada']);
            $offer->status = 'aceite';
            $offer->save();
            $locked->status = 'proposta_selecionada';
            $locked->purchase_config = $conditions;
            $locked->save();
            PlatformAudit::record($actor, 'proposal.selected', $offer, ['campaignId' => $locked->id]);
            if ($supplier->user) {
                $this->notifier->send($supplier->user, 'proposal.selected:'.$offer->id, 'Proposta seleccionada', 'A sua proposta para '.$locked->title.' foi seleccionada.', route('fornecedor.propostas.show', $offer, absolute: false));
            }
            foreach ($locked->participations()->with('user')->where('status', '!=', 'cancelada')->get() as $participation) {
                if ($participation->user) {
                    $this->notifier->send($participation->user, 'proposal.selected:'.$offer->id, 'Fornecedor seleccionado', 'Foi seleccionada uma proposta para '.$locked->title.'. Aguarde a abertura dos pagamentos.', route('consumidor.acompanhamento.show', ['id' => $locked->id], absolute: false), 'notifProposal');
                }
            }
        }, 5);
    }
}
