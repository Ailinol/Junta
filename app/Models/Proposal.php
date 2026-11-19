<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property int $campaign_id
 * @property int $supplier_id
 * @property numeric-string $unit_price
 * @property int $available_quantity
 * @property numeric-string $total_estimated
 * @property int $delivery_days
 * @property string $delivery_mode
 * @property CarbonInterface $valid_until
 * @property CarbonInterface $submitted_at
 * @property string $status
 * @property string|null $notes
 * @property-read Supplier $supplier
 * @property-read Campaign $campaign
 * @property int|null $minimum_quantity
 * @property CarbonInterface|null $reserved_until
 * @property bool|null $transport_included
 * @property int|null $transport_unit_cents
 * @property CarbonInterface|null $supplier_confirmed_at
 */
#[Fillable(['campaign_id', 'supplier_id', 'unit_price', 'available_quantity', 'total_estimated', 'delivery_days', 'delivery_mode', 'valid_until', 'status', 'notes', 'submitted_at', 'minimum_quantity', 'reserved_until', 'transport_included', 'transport_unit_cents'])]
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'available_quantity' => 'integer', 'total_estimated' => 'decimal:2', 'delivery_days' => 'integer', 'valid_until' => 'datetime', 'submitted_at' => 'datetime', 'minimum_quantity' => 'integer', 'reserved_until' => 'datetime', 'transport_included' => 'boolean', 'transport_unit_cents' => 'integer', 'supplier_confirmed_at' => 'datetime'];
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
