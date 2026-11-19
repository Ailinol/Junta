<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\FinancialEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FinancialEntry> */
class FinancialEntryFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['campaign_id' => Campaign::factory(), 'event_key' => (string) Str::uuid(), 'kind' => 'reconciliation_required', 'amount_cents' => 0, 'currency' => 'MZN', 'reference' => '[TESTE] '.fake()->uuid(), 'reason' => '[TESTE] Conferência pendente.'];
    }
}
