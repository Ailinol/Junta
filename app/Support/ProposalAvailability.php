<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Proposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalAvailability
{
    public const ACTIVE_STATUSES = ['rascunho', 'submetida', 'em_avaliacao', 'aceite'];

    /** Serializes offer writes with selection, including SQLite transactions. */
    public function lockCampaign(int $id): Campaign
    {
        Campaign::query()->whereKey($id)->update(['held_quantity' => DB::raw('held_quantity')]);

        return Campaign::query()->lockForUpdate()->findOrFail($id);
    }

    public function assertOpen(Campaign $campaign): void
    {
        if (! $campaign->canReceiveProposals()) {
            throw ValidationException::withMessages(['campaignId' => 'Esta oportunidade já não aceita propostas.']);
        }
    }

    /** Call only while holding the campaign lock in the writing transaction. */
    public function assertCanWrite(Campaign $campaign, int $supplierId, string $status, ?int $exceptId = null): void
    {
        $this->assertOpen($campaign);
        if (in_array($status, self::ACTIVE_STATUSES, true) && Proposal::query()
            ->where('campaign_id', $campaign->id)->where('supplier_id', $supplierId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            throw ValidationException::withMessages(['campaignId' => 'Já existe uma proposta activa deste fornecedor nesta campanha. Edite ou retire a proposta existente.']);
        }
    }
}
