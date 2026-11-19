<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'company_name' => fake()->company(), 'nuit' => fake()->unique()->numerify('#########'), 'contact_name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'phone' => '+258 84 222 3344', 'location' => 'Maputo', 'categories' => ['construcao'], 'description' => 'Capacidade de fornecimento indicada pelo fornecedor.', 'verified' => false];
    }
}
