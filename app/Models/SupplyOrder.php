<?php

namespace App\Models;

use Database\Factories\SupplyOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property int $campaign_id
 * @property int $supplier_id
 * @property string $reference
 * @property int $quantity
 * @property int $received_quantity
 * @property string $status
 * @property array<string,mixed> $conditions
 * @property-read Campaign $campaign
 * @property-read Supplier $supplier
 */
#[Fillable(['campaign_id', 'supplier_id', 'reference', 'quantity', 'received_quantity', 'status', 'conditions'])]
class SupplyOrder extends Model
{
    /** @use HasFactory<SupplyOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'received_quantity' => 'integer', 'conditions' => 'array'];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
