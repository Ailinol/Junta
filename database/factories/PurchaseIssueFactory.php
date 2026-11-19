<?php

namespace Database\Factories;

use App\Models\PurchaseIssue;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseIssue> */
class PurchaseIssueFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'user_id' => fn (array $attributes) => PurchaseOrder::query()->whereKey($attributes['purchase_order_id'])->firstOrFail()->user_id, 'type' => 'missing', 'description' => '[TESTE] Mercadoria em falta.', 'status' => 'open'];
    }
}
