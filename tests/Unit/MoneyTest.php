<?php

use App\Support\Money;

test('M01 monetary input becomes exact integer cents', function (string|int $input, int $expected) {
    $result = Money::cents($input);

    expect($result)->toBe($expected);
})->with([
    'zero' => ['0', 0],
    'smallest fraction' => ['0.01', 1],
    'one decimal place' => ['12.3', 1230],
    'two decimal places' => ['12.34', 1234],
    'whole number' => [125, 12500],
    'largest supported textual amount' => ['9999999999.99', 999999999999],
]);

test('M02 merchandise multiplication preserves the supported monetary boundary', function (int $cents, int $quantity, int $expected) {
    $result = Money::multiply($cents, $quantity);

    expect($result)->toBe($expected);
})->with([
    'ordinary purchase' => [12550, 3, 37650],
    'exact monetary limit' => [500000000000, 2, 1000000000000],
    'zero cost' => [0, PHP_INT_MAX, 0],
]);

test('M03 commission rounds half cents up using basis points', function (int $subtotal, int $basisPoints, int $expected) {
    $result = Money::commission($subtotal, $basisPoints);

    expect($result)->toBe($expected);
})->with([
    'standard platform rate' => [100000, 250, 2500],
    'below half cent' => [19, 250, 0],
    'exactly half cent' => [20, 250, 1],
    'above half cent' => [21, 250, 1],
    'explicit zero commission' => [10000, 0, 0],
    'maximum configured rate' => [1000000000000, 10000, 1000000000000],
]);

test('M04 display amounts retain two decimal places without floating point conversion', function (int $cents, string $expected) {
    $result = Money::decimal($cents);

    expect($result)->toBe($expected);
})->with([
    'zero' => [0, '0.00'],
    'one cent' => [1, '0.01'],
    'whole amount' => [12300, '123.00'],
    'purchase limit' => [1000000000000, '10000000000.00'],
]);

test('M05 a negative monetary display value is rejected', function () {
    $call = fn () => Money::decimal(-1);

    expect($call)->toThrow(InvalidArgumentException::class);
});
