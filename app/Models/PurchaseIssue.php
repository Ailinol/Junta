<?php

namespace App\Models;

use Database\Factories\PurchaseIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property int $purchase_order_id
 * @property int $user_id
 * @property string $type
 * @property string $description
 * @property string $status
 * @property string|null $resolution
 * @property-read PurchaseOrder $purchaseOrder
 */
#[Fillable(['purchase_order_id', 'user_id', 'type', 'description', 'status', 'resolution'])]
class PurchaseIssue extends Model
{
    /** @use HasFactory<PurchaseIssueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
