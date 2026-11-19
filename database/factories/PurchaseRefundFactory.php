<?php

namespace Database\Factories;

use App\Models\PaymentAttempt;
use App\Models\PurchaseRefund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseRefund> */
class PurchaseRefundFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['payment_attempt_id' => PaymentAttempt::factory(), 'reference' => (string) Str::uuid(), 'amount_cents' => fn (array $attributes) => PaymentAttempt::query()->whereKey($attributes['payment_attempt_id'])->firstOrFail()->amount_cents, 'status' => 'requested', 'reason' => '[TESTE] Reembolso a conferir.'];
    }
}
