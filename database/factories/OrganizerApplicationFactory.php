<?php

namespace Database\Factories;

use App\Models\OrganizerApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizerApplication> */
class OrganizerApplicationFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'full_name' => fake()->name(), 'phone' => '+258 84 123 4567', 'location' => 'Maputo', 'motivation' => 'Pretendo organizar as compras da minha comunidade.', 'status' => 'pending'];
    }
}
