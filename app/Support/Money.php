<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Money
{
    public const MAX_CENTS = 1_000_000_000_000;

    public static function cents(string|int $value): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw ValidationException::withMessages(['amount' => 'Use um valor em MZN com até duas casas decimais.']);
        }
        $parts = explode('.', (string) $value);

        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function multiply(int $cents, int $quantity): int
    {
        if ($cents < 0 || $quantity < 1 || ($cents > 0 && $quantity > intdiv(self::MAX_CENTS, $cents))) {
            throw ValidationException::withMessages(['amount' => 'O pedido excede o limite monetário do piloto.']);
        }

        return $cents * $quantity;
    }

    public static function commission(int $subtotal, int $basisPoints): int
    {
        return intdiv($subtotal * $basisPoints + 5000, 10000);
    }

    /** @return numeric-string */
    public static function decimal(int $cents): string
    {
        $decimal = sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
        if (! is_numeric($decimal) || $cents < 0) {
            throw new \InvalidArgumentException('O valor monetário deve ser não negativo.');
        }

        return $decimal;
    }
}
