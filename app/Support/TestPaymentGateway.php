<?php

namespace App\Support;

use App\Models\TestGatewayTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TestPaymentGateway implements PaymentGateway
{
    public function available(): bool
    {
        $simulationAllowed = app()->environment(['local', 'testing'])
            || (app()->isProduction() && config('app.demo_mode') === true);

        return $simulationAllowed && config('purchases.provider') === 'test' && config('purchases.test_enabled') === true;
    }

    public function assertAvailable(): void
    {
        if (! $this->available()) {
            throw ValidationException::withMessages(['payment' => 'Checkout indisponível: falta um prestador de pagamentos autorizado.']);
        }
    }

    /** @return list<array{code:string,label:string,requiresPhone:bool}> */
    public function availableMethods(): array
    {
        return $this->available() ? [
            ['code' => 'mpesa', 'label' => 'M-Pesa', 'requiresPhone' => true],
            ['code' => 'emola', 'label' => 'e-Mola', 'requiresPhone' => true],
            ['code' => 'mkesh', 'label' => 'mKesh', 'requiresPhone' => true],
            ['code' => 'card', 'label' => 'Cartão', 'requiresPhone' => false],
        ] : [];
    }

    public function initiate(string $reference, int $amountCents, string $currency, ?string $method = null, ?string $phone = null): void
    {
        $this->assertAvailable();
        TestGatewayTransaction::firstOrCreate(['reference' => $reference], ['kind' => 'charge', 'amount_cents' => $amountCents, 'currency' => $currency, 'status' => 'pending']);
    }

    public function lookup(string $reference): ?array
    {
        $this->assertAvailable();
        $transaction = TestGatewayTransaction::query()->where('reference', $reference)->first();

        return $transaction ? ['reference' => $transaction->reference, 'status' => $transaction->status, 'amount_cents' => $transaction->amount_cents, 'currency' => $transaction->currency, 'settled_at' => $transaction->settled_at?->toIso8601String()] : null;
    }

    public function simulate(string $reference, string $status): void
    {
        $this->assertAvailable();
        DB::transaction(function () use ($reference, $status): void {
            $transaction = TestGatewayTransaction::query()->where('reference', $reference)->where('kind', 'charge')->lockForUpdate()->firstOrFail();
            if ($transaction->status === 'pending' && in_array($status, ['paid', 'failed'], true)) {
                $transaction->status = $status;
                $transaction->settled_at = now();
                $transaction->save();
            }
        });
    }

    public function refund(string $paymentReference, string $refundReference, int $amountCents): bool
    {
        $this->assertAvailable();
        $payment = $this->lookup($paymentReference);
        if (! $payment || $payment['status'] !== 'paid' || $payment['amount_cents'] !== $amountCents || $payment['currency'] !== 'MZN') {
            return false;
        }
        TestGatewayTransaction::firstOrCreate(['reference' => $refundReference], ['kind' => 'refund', 'amount_cents' => $amountCents, 'currency' => 'MZN', 'status' => 'refunded']);

        return true;
    }
}
