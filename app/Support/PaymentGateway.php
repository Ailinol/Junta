<?php

namespace App\Support;

interface PaymentGateway
{
    public function available(): bool;

    /** @return list<array{code:string,label:string,requiresPhone:bool}> */
    public function availableMethods(): array;

    /** Reusing a reference must resume the same charge, never create another charge. */
    public function initiate(string $reference, int $amountCents, string $currency, ?string $method = null, ?string $phone = null): void;

    /** @return array{reference:string,status:string,amount_cents:int,currency:string,settled_at:?string}|null */
    public function lookup(string $reference): ?array;

    /** Reusing a refund reference must not issue a second refund. */
    public function refund(string $paymentReference, string $refundReference, int $amountCents): bool;
}
