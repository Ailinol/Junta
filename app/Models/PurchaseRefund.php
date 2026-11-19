<?php

namespace App\Models;

use Database\Factories\PurchaseRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property positive-int $id
 * @property int $payment_attempt_id
 * @property string $reference
 * @property int $amount_cents
 * @property string $status
 * @property string $reason
 * @property-read PaymentAttempt $paymentAttempt
 */
#[Fillable(['payment_attempt_id', 'reference', 'amount_cents', 'status', 'reason'])]
class PurchaseRefund extends Model
{
    /** @use HasFactory<PurchaseRefundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }
}
