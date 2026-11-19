<?php

namespace Database\Factories;

use App\Models\Participation;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'participation_id' => Participation::factory(),
            'campaign_id' => fn (array $attributes) => Participation::query()->whereKey($attributes['participation_id'])->firstOrFail()->campaign_id,
            'user_id' => fn (array $attributes) => Participation::query()->whereKey($attributes['participation_id'])->firstOrFail()->user_id,
            'reference' => (string) Str::uuid(), 'quantity' => 1, 'status' => 'awaiting_payment', 'receipt_status' => 'waiting',
            'delivery_method' => 'recolha', 'currency' => 'MZN', 'merchandise_cents' => 10000, 'commission_cents' => 0, 'supplier_transport_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 10000,
            'conditions' => ['unit_cents' => 10000, 'minimum_quantity' => 1, 'available_quantity' => 10, 'commission_bps' => 0, 'pickup_point' => '[TESTE] Maputo', 'delivery_zone' => '', 'delivery_fee_cents' => 0, 'transport_unit_cents' => 0, 'transport_included' => true, 'transport_distribution' => 'fixed_per_unit', 'delivery_days' => 3, 'price_valid_until' => now()->addDays(3)->toIso8601String(), 'reserved_until' => now()->addDays(3)->toIso8601String(), 'terms_version' => config('purchases.terms_version'), 'terms' => config('purchases.terms')],
            'terms_version' => config('purchases.terms_version'), 'accepted_at' => now(), 'payment_deadline' => now()->addDay(),
        ];
    }
}
