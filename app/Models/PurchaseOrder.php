<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property positive-int $id
 * @property int $campaign_id
 * @property int $participation_id
 * @property int $user_id
 * @property string $reference
 * @property int $quantity
 * @property string $status
 * @property string $receipt_status
 * @property string $delivery_method
 * @property string|null $delivery_address
 * @property string $currency
 * @property int $merchandise_cents
 * @property int $commission_cents
 * @property int $supplier_transport_cents
 * @property int $delivery_fee_cents
 * @property int $total_cents
 * @property array<string,mixed> $conditions
 * @property string $terms_version
 * @property CarbonInterface|null $accepted_at
 * @property CarbonInterface $payment_deadline
 * @property string|null $collection_code_hash
 * @property string|null $collection_code
 * @property CarbonInterface|null $received_at
 * @property-read Campaign $campaign
 * @property-read User $user
 * @property-read Participation $participation
 * @property-read Collection<int, PaymentAttempt> $attempts
 * @property-read Collection<int, PurchaseIssue> $issues
 */
#[Fillable(['campaign_id', 'participation_id', 'user_id', 'reference', 'quantity', 'status', 'receipt_status', 'delivery_method', 'delivery_address', 'currency', 'merchandise_cents', 'commission_cents', 'supplier_transport_cents', 'delivery_fee_cents', 'total_cents', 'conditions', 'terms_version', 'accepted_at', 'payment_deadline', 'collection_code_hash', 'collection_code', 'received_at'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    protected $hidden = ['collection_code', 'collection_code_hash'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'merchandise_cents' => 'integer', 'commission_cents' => 'integer', 'supplier_transport_cents' => 'integer', 'delivery_fee_cents' => 'integer', 'total_cents' => 'integer', 'conditions' => 'array', 'accepted_at' => 'datetime', 'payment_deadline' => 'datetime', 'received_at' => 'datetime', 'collection_code' => 'encrypted'];
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->orderByDesc('id');
    }

    /** @return HasMany<PurchaseIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(PurchaseIssue::class);
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Participation, $this> */
    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }
}
