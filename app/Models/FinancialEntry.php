<?php

namespace App\Models;

use Database\Factories\FinancialEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property int $campaign_id
 * @property int|null $purchase_order_id
 * @property int|null $user_id
 * @property string $event_key
 * @property string $kind
 * @property int $amount_cents
 * @property string $currency
 * @property string $reference
 * @property string $reason
 * @property-read Campaign $campaign
 */
#[Fillable(['campaign_id', 'purchase_order_id', 'user_id', 'event_key', 'kind', 'amount_cents', 'currency', 'reference', 'reason'])]
class FinancialEntry extends Model
{
    /** @use HasFactory<FinancialEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
