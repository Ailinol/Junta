<?php

use App\Support\DeliveryFeeAllocation;
use App\Support\Money;

test('shared delivery follows quantities with deterministic cents and the original total', function (int $totalCents, array $participants, array $expected) {
    $shares = DeliveryFeeAllocation::allocate($totalCents, $participants);

    expect($shares)->toBe($expected);
    expect(array_sum($shares))->toBe($totalCents);
})->with([
    'quantity proportional shares' => [10000, [['id' => 1, 'quantity' => 3], ['id' => 2, 'quantity' => 2]], [1 => 6000, 2 => 4000]],
    'one remaining cent follows stable participant ids' => [10000, [['id' => 9, 'quantity' => 1], ['id' => 4, 'quantity' => 1], ['id' => 7, 'quantity' => 1]], [4 => 3334, 7 => 3333, 9 => 3333]],
    'fewer cents than participants' => [2, [['id' => 4, 'quantity' => 1], ['id' => 3, 'quantity' => 1], ['id' => 2, 'quantity' => 1], ['id' => 1, 'quantity' => 1]], [1 => 1, 2 => 1, 3 => 0, 4 => 0]],
    'single participant' => [12345, [['id' => 9, 'quantity' => 20]], [9 => 12345]],
    'free delivery' => [0, [['id' => 2, 'quantity' => 3], ['id' => 1, 'quantity' => 1]], [1 => 0, 2 => 0]],
    'large quantities without integer overflow or floating point loss' => [1000000000000, [['id' => 3, 'quantity' => 2000000000], ['id' => 1, 'quantity' => 2000000000], ['id' => 2, 'quantity' => 2000000000]], [1 => 333333333334, 2 => 333333333333, 3 => 333333333333]],
    'largest fractional remainder is allocated first' => [100, [['id' => 1, 'quantity' => 1], ['id' => 2, 'quantity' => 2], ['id' => 3, 'quantity' => 3]], [1 => 17, 2 => 33, 3 => 50]],
]);

test('an empty group can only have zero shared delivery cost', function () {
    expect(DeliveryFeeAllocation::allocate(0, []))->toBe([]);
});

test('invalid delivery allocation inputs cannot lose money or duplicate a participant', function (int $totalCents, array $participants) {
    expect(fn () => DeliveryFeeAllocation::allocate($totalCents, $participants))->toThrow(InvalidArgumentException::class);
})->with([
    'negative cost' => [-1, [['id' => 1, 'quantity' => 1]]],
    'cost above the purchase monetary limit' => [Money::MAX_CENTS + 1, [['id' => 1, 'quantity' => 1]]],
    'positive cost without participants' => [100, []],
    'duplicate participant' => [100, [['id' => 1, 'quantity' => 1], ['id' => 1, 'quantity' => 2]]],
    'zero participant id' => [100, [['id' => 0, 'quantity' => 1]]],
    'negative participant id' => [100, [['id' => -1, 'quantity' => 1]]],
    'zero quantity' => [100, [['id' => 1, 'quantity' => 0]]],
    'negative quantity' => [100, [['id' => 1, 'quantity' => -1]]],
]);
