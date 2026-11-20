<?php

namespace App\Support;

use InvalidArgumentException;

class DeliveryFeeAllocation
{
    /**
     * @param  list<array{id:int,quantity:int}>  $participants
     * @return array<int, int>
     */
    public static function allocate(int $totalCents, array $participants): array
    {
        if ($totalCents < 0 || $totalCents > Money::MAX_CENTS) {
            throw new InvalidArgumentException('O custo da entrega excede o intervalo suportado.');
        }
        $quantities = [];
        $totalQuantity = 0;
        foreach ($participants as $participant) {
            $id = $participant['id'];
            $quantity = $participant['quantity'];
            if ($id < 1 || $quantity < 1 || isset($quantities[$id]) || $quantity > PHP_INT_MAX - $totalQuantity) {
                throw new InvalidArgumentException('Os participantes e quantidades do rateio não são válidos.');
            }
            $quantities[$id] = $quantity;
            $totalQuantity += $quantity;
        }
        if ($totalQuantity === 0) {
            if ($totalCents !== 0) {
                throw new InvalidArgumentException('Uma entrega com custo exige participantes.');
            }

            return [];
        }
        ksort($quantities, SORT_NUMERIC);
        $shares = [];
        $remainders = [];
        foreach ($quantities as $id => $quantity) {
            $numerator = bcmul((string) $totalCents, (string) $quantity, 0);
            $shares[$id] = (int) bcdiv($numerator, (string) $totalQuantity, 0);
            $remainders[$id] = (int) bcmod($numerator, (string) $totalQuantity, 0);
        }
        $remaining = $totalCents - array_sum($shares);
        $priority = array_keys($quantities);
        usort($priority, fn (int $left, int $right): int => ($remainders[$right] <=> $remainders[$left]) ?: ($left <=> $right));
        for ($index = 0; $index < $remaining; $index++) {
            $shares[$priority[$index]]++;
        }

        return $shares;
    }
}
