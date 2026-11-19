<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SupplyOrder> */
class SupplyOrderFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['campaign_id' => Campaign::factory(), 'supplier_id' => Supplier::factory(), 'reference' => (string) Str::uuid(), 'quantity' => 10, 'received_quantity' => 0, 'status' => 'issued', 'conditions' => ['source' => 'test']];
    }
}
