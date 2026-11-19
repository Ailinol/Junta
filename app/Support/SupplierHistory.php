<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SupplierHistory
{
    /**
     * @param  list<int>  $supplierIds
     * @return array<int,array{orders:int,fullyReceived:int,partial:int,finished:int,inProgress:int,score:float,onTimeRate:null}>
     */
    public function forComparison(array $supplierIds, int $excludeCampaignId): array
    {
        $rows = DB::table('supply_orders as supplies')->join('campaigns', 'campaigns.id', '=', 'supplies.campaign_id')
            ->whereIn('supplies.supplier_id', $supplierIds)->where('supplies.campaign_id', '!=', $excludeCampaignId)
            ->select('supplies.supplier_id')->selectRaw("count(*) as orders,
                sum(case when supplies.received_quantity >= supplies.quantity and supplies.quantity > 0 then 1 else 0 end) as fully_received,
                sum(case when supplies.received_quantity > 0 and supplies.received_quantity < supplies.quantity then 1 else 0 end) as partial,
                sum(case when (supplies.received_quantity >= supplies.quantity and supplies.quantity > 0)
                    or campaigns.purchase_phase in ('completed','cancelled') or campaigns.status in ('concluida','cancelada') then 1 else 0 end) as finished")
            ->groupBy('supplies.supplier_id')->get()->keyBy('supplier_id');
        $history = [];
        foreach (array_unique($supplierIds) as $id) {
            $row = $rows->get($id);
            $orders = (int) ($row->orders ?? 0);
            $finished = (int) ($row->finished ?? 0);
            $received = (int) ($row->fully_received ?? 0);
            $history[$id] = ['orders' => $orders, 'fullyReceived' => $received, 'partial' => (int) ($row->partial ?? 0),
                'finished' => $finished, 'inProgress' => $orders - $finished,
                'score' => round(100 * ($received + 1) / ($finished + 2), 1), 'onTimeRate' => null];
        }

        return $history;
    }
}
