<?php

namespace Database\Factories;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditEntry> */
class AuditEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'actor_name' => 'Administrador', 'action' => 'test.action', 'subject_type' => 'Campaign', 'subject_id' => 1, 'changes' => []];
    }
}
