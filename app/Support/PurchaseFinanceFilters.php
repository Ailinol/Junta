<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PurchaseFinanceFilters
{
    /** @return array{search:string,campaignId:?int,from:?string,to:?string,type:?string} */
    public static function validated(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'], 'campaignId' => ['nullable', 'integer', 'exists:campaigns,id'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from') ? 'after_or_equal:from' : null]),
            'type' => ['nullable', 'string', 'max:80'],
        ]);

        return ['search' => trim($data['search'] ?? ''), 'campaignId' => isset($data['campaignId']) ? (int) $data['campaignId'] : null, 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null, 'type' => $data['type'] ?? null];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{search:string,campaignId:?int,from:?string,to:?string,type:?string}  $filters
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, array $filters, bool $events = false): Builder
    {
        if ($events && ! empty($filters['type'])) {
            $query->where('kind', $filters['type']);
        }
        if ($filters['campaignId'] !== null) {
            $query->where('campaign_id', $filters['campaignId']);
        }
        if ($filters['from'] !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], 'Africa/Maputo')->startOfDay()->utc());
        }
        if ($filters['to'] !== null) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'], 'Africa/Maputo')->addDay()->startOfDay()->utc());
        }
        if ($filters['search'] !== '') {
            $query->where(function (Builder $query) use ($filters, $events): void {
                $search = '%'.$filters['search'].'%';
                $query->where('reference', 'like', $search);
                if ($events) {
                    $query->orWhere('reason', 'like', $search);
                }
                $query->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('title', 'like', $search));
            });
        }

        return $query;
    }
}
