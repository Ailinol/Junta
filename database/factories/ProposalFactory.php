<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Proposal> */
class ProposalFactory extends Factory
{
    public function definition(): array
    {
        return ['campaign_id' => Campaign::factory(), 'supplier_id' => Supplier::factory()->state(['verified' => true]), 'unit_price' => 800, 'available_quantity' => 100, 'minimum_quantity' => 1, 'reserved_until' => now()->addDays(5), 'transport_included' => true, 'transport_unit_cents' => 0, 'total_estimated' => 80000, 'delivery_days' => 5, 'delivery_mode' => 'entrega', 'valid_until' => now()->addDays(5), 'status' => 'submetida', 'submitted_at' => now()];
    }
}
