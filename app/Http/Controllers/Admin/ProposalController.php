<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProposalRequest;
use App\Models\Proposal;
use App\Support\PlatformAudit;
use App\Support\ProposalAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProposalController extends Controller
{
    public function __construct(private ProposalAvailability $availability) {}

    public function store(ProposalRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $campaign = $this->availability->lockCampaign($request->integer('campaignId'));
            $data = $request->proposalData();
            $this->availability->assertCanWrite($campaign, $data['supplier_id'], $data['status']);
            $data['submitted_at'] = now();
            $proposal = Proposal::create($data);
            PlatformAudit::record($request->user(), 'proposal.created', $proposal);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposta criada.']);

        return back();
    }

    public function update(ProposalRequest $request, Proposal $proposal): RedirectResponse
    {
        DB::transaction(function () use ($request, $proposal): void {
            if ($request->integer('campaignId') !== $proposal->campaign_id) {
                throw ValidationException::withMessages(['campaignId' => 'A proposta não pode ser transferida para outra campanha.']);
            }
            $campaign = $this->availability->lockCampaign($proposal->campaign_id);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status === 'aceite') {
                throw ValidationException::withMessages(['record' => 'As condições de uma proposta seleccionada não podem ser alteradas por este formulário.']);
            }
            $before = $locked->toArray();
            $data = $request->proposalData();
            $this->availability->assertCanWrite($campaign, $data['supplier_id'], $data['status'], $locked->id);
            $locked->update($data);
            PlatformAudit::record($request->user(), 'proposal.updated', $locked, ['before' => $before, 'after' => $locked->toArray()]);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposta actualizada.']);

        return back();
    }

    public function destroy(Request $request, Proposal $proposal): RedirectResponse
    {
        DB::transaction(function () use ($request, $proposal): void {
            $this->availability->lockCampaign($proposal->campaign_id);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status === 'aceite') {
                throw ValidationException::withMessages(['record' => 'Uma proposta seleccionada faz parte da execução e não pode ser removida.']);
            }
            PlatformAudit::record($request->user(), 'proposal.deleted', $locked, ['campaignId' => $locked->campaign_id, 'supplierId' => $locked->supplier_id]);
            $locked->delete();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposta removida.']);

        return back();
    }
}
