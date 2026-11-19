<?php

namespace Database\Factories;

use App\Models\TestGatewayTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TestGatewayTransaction> */
class TestGatewayTransactionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['reference' => (string) Str::uuid(), 'kind' => 'charge', 'status' => 'pending', 'amount_cents' => 10000, 'currency' => 'MZN'];
    }
}
