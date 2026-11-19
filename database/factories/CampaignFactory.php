<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campaign> */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return ['organizer_id' => User::factory()->state(['is_organizer' => true]), 'organizer_name' => 'Organizador', 'title' => 'Campanha de cimento', 'description' => 'Compra colectiva de cimento para construção.', 'category' => 'construcao', 'status' => 'ativa', 'target_quantity' => 100, 'unit' => 'sacos', 'estimated_price' => 850, 'deadline' => now()->addDays(10), 'location' => 'Maputo', 'delivery_mode' => 'recolha'];
    }
}
