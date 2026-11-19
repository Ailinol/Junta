<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProposalRequest;
use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\SupplyOrder;
use App\Support\PlatformAudit;
use App\Support\PlatformNotifier;
use App\Support\ProposalAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function __construct(private ProposalAvailability $availability) {}

    public function show(Request $request, Proposal $proposal): Response
    {
        abort_unless($proposal->supplier->user_id === $request->user()->id, 404);
        $supply = $proposal->status === 'aceite' ? SupplyOrder::query()->where('campaign_id', $proposal->campaign_id)->first(['quantity', 'received_quantity', 'status']) : null;

        return Inertia::render('fornecedor/propostas/detalhes', ['proposalId' => $proposal->id, 'supply' => $supply?->toArray()]);
    }

    public function legacyConfirmation(Request $request, Campaign $campaign): RedirectResponse
    {
        $proposal = $campaign->proposals()->where('status', 'aceite')->whereHas('supplier', fn ($supplier) => $supplier->where('user_id', $request->user()->id)->where('verified', true))->firstOrFail();

        return to_route('fornecedor.propostas.show', $proposal, 301);
    }

    public function reportProblem(Request $request, Proposal $proposal, PlatformNotifier $notifier): RedirectResponse
    {
        abort_unless($proposal->supplier->user_id === $request->user()->id, 404);
        $data = $request->validate(['reason' => ['required', Rule::in(['stock', 'deadline', 'transport', 'other'])], 'description' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $proposal, $data, $notifier): void {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($proposal->campaign_id);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'aceite') {
                throw ValidationException::withMessages(['proposal' => 'Só pode comunicar um problema numa proposta seleccionada.']);
            }
            if (! $campaign->organizer) {
                throw ValidationException::withMessages(['proposal' => 'Esta campanha antiga não tem um organizador associado para receber o aviso. Contacte a administração.']);
            }
            PlatformAudit::record($request->user(), 'proposal.problem_reported', $locked, $data);
            $reasons = ['stock' => 'Disponibilidade da mercadoria', 'deadline' => 'Prazo de entrega', 'transport' => 'Transporte', 'other' => 'Outro motivo'];
            $notifier->send($campaign->organizer, 'proposal.problem:'.$locked->id.':'.Str::uuid(), 'Problema com a proposta seleccionada', $reasons[$data['reason']].': '.$data['description'], route('organizador.campanhas.propostas', ['id' => $campaign->id], absolute: false));
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'O organizador foi notificado. As condições e os pagamentos não foram alterados.']);

        return back();
    }

    public function edit(Request $request, Proposal $proposal): Response|RedirectResponse
    {
        abort_unless($proposal->supplier->user_id === $request->user()->id, 404);
        if (! $proposal->campaign->canReceiveProposals() || ! in_array($proposal->status, ['rascunho', 'submetida', 'em_avaliacao'], true)) {
            return to_route('fornecedor.propostas.show', $proposal)->withErrors(['proposal' => 'Esta proposta já não pode ser alterada. As condições seleccionadas são o seu compromisso.']);
        }

        return Inertia::render('fornecedor/propostas/criar', ['editingProposalId' => $proposal->id]);
    }

    public function store(ProposalRequest $request, PlatformNotifier $notifier): RedirectResponse
    {
        DB::transaction(function () use ($request, $notifier): void {
            $campaign = $this->availability->lockCampaign($request->integer('campaignId'));
            $this->ensureOpen($campaign);
            $data = $request->proposalData();
            $this->availability->assertCanWrite($campaign, $data['supplier_id'], 'submetida');
            $data['submitted_at'] = now();
            $proposal = Proposal::create($data);
            PlatformAudit::record($request->user(), 'proposal.created', $proposal);
            if ($campaign->organizer) {
                $notifier->send($campaign->organizer, 'proposal.created:'.$proposal->id, 'Nova proposta recebida', 'Recebeu uma proposta para '.$campaign->title.'.', route('organizador.campanhas.propostas', ['id' => $campaign->id], absolute: false));
            }
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposta submetida.']);

        return to_route('fornecedor.propostas.index');
    }

    public function update(ProposalRequest $request, Proposal $proposal, PlatformNotifier $notifier): RedirectResponse
    {
        abort_unless($proposal->supplier->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $proposal, $notifier): void {
            $campaign = $this->availability->lockCampaign($proposal->campaign_id);
            $this->ensureOpen($campaign);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($request->integer('campaignId') !== $campaign->id || ! in_array($locked->status, ['rascunho', 'submetida', 'em_avaliacao'], true)) {
                throw ValidationException::withMessages(['proposal' => 'Esta proposta já não pode ser alterada.']);
            }
            $before = $locked->toArray();
            $data = $request->proposalData();
            $this->availability->assertCanWrite($campaign, $data['supplier_id'], 'submetida', $locked->id);
            unset($data['campaign_id'], $data['supplier_id']);
            $data['status'] = 'submetida';
            $locked->update($data);
            PlatformAudit::record($request->user(), 'proposal.updated', $locked, ['before' => $before, 'after' => $locked->toArray()]);
            if ($campaign->organizer) {
                $notifier->send($campaign->organizer, 'proposal.updated:'.$locked->id.':'.$locked->updated_at->format('U.u'), 'Proposta actualizada', 'Um fornecedor reviu as condições para '.$campaign->title.'. Compare novamente antes de seleccionar.', route('organizador.campanhas.propostas', ['id' => $campaign->id], absolute: false));
            }
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposta actualizada.']);

        return to_route('fornecedor.propostas.index');
    }

    public function withdraw(Request $request, Proposal $proposal, PlatformNotifier $notifier): RedirectResponse
    {
        abort_unless($proposal->supplier->user_id === $request->user()->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $proposal, $data, $notifier): void {
            $campaign = $this->availability->lockCampaign($proposal->campaign_id);
            $this->ensureOpen($campaign);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if (! in_array($locked->status, ['rascunho', 'submetida', 'em_avaliacao'], true)) {
                throw ValidationException::withMessages(['proposal' => 'Uma proposta seleccionada ou encerrada não pode ser retirada.']);
            }
            $locked->status = 'retirada';
            $locked->save();
            PlatformAudit::record($request->user(), 'proposal.withdrawn', $locked, ['reason' => $data['reason']]);
            if ($campaign->organizer) {
                $notifier->send($campaign->organizer, 'proposal.withdrawn:'.$locked->id, 'Proposta retirada', 'O fornecedor retirou a proposta para '.$campaign->title.'. Motivo: '.$data['reason'], route('organizador.campanhas.propostas', ['id' => $campaign->id], absolute: false));
            }
        }, 5);

        return back();
    }

    private function ensureOpen(Campaign $campaign): void
    {
        $this->availability->assertOpen($campaign);
    }
}
