<?php

namespace Database\Factories;

use App\Models\PaymentAttempt;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PaymentAttempt> */
class PaymentAttemptFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'reference' => (string) Str::uuid(), 'provider' => 'test', 'status' => 'pending', 'amount_cents' => fn (array $attributes) => PurchaseOrder::query()->whereKey($attributes['purchase_order_id'])->firstOrFail()->total_cents, 'currency' => 'MZN', 'holds_stock' => false];
    }
}
