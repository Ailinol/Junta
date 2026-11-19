<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Participation> */
class ParticipationFactory extends Factory
{
    public function definition(): array
    {
        return ['campaign_id' => Campaign::factory(), 'user_id' => User::factory(), 'quantity' => 5, 'status' => 'pendente'];
    }
}
