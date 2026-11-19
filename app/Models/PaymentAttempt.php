<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property positive-int $id
 * @property int $purchase_order_id
 * @property string $reference
 * @property string $provider
 * @property string $status
 * @property int $amount_cents
 * @property string $currency
 * @property bool $holds_stock
 * @property string|null $payment_method
 * @property string|null $payment_phone
 * @property CarbonInterface|null $next_check_at
 * @property CarbonInterface|null $escalated_at
 * @property CarbonInterface|null $confirmed_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read PurchaseRefund|null $refund
 */
#[Fillable(['purchase_order_id', 'reference', 'provider', 'status', 'amount_cents', 'currency', 'holds_stock', 'confirmed_at', 'payment_method', 'payment_phone', 'next_check_at', 'escalated_at'])]
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected $hidden = ['payment_phone'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'holds_stock' => 'boolean', 'confirmed_at' => 'datetime', 'payment_phone' => 'encrypted', 'next_check_at' => 'datetime', 'escalated_at' => 'datetime'];
    }

    /** @return HasOne<PurchaseRefund, $this> */
    public function refund(): HasOne
    {
        return $this->hasOne(PurchaseRefund::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
