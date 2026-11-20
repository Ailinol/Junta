<?php

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

test('monetary parsing refuses anomalous amounts with a validation error', function (string $input) {
    $call = fn () => Money::cents($input);

    expect($call)->toThrow(ValidationException::class);
})->with(['', '-1', '1.234', '1,50', '1e3', ' 1.00', '1.00 ', 'NaN', '10000000000.00']);

test('merchandise multiplication refuses invalid quantities and overflow', function (int $cents, int $quantity) {
    $call = fn () => Money::multiply($cents, $quantity);

    expect($call)->toThrow(ValidationException::class);
})->with([
    'negative amount' => [-1, 1],
    'zero quantity' => [100, 0],
    'negative quantity' => [100, -1],
    'overflow beyond purchase limit' => [500000000001, 2],
]);

test('EXP01 characterizes the pending mismatch between accepted proposal price and selectable monetary amount', function () {
    $this->freezeTime();
    $supplierUser = User::factory()->create();
    $supplier = Supplier::factory()->for($supplierUser)->create(['verified' => true]);
    $campaign = Campaign::factory()->create(['status' => 'em_avaliacao']);
    $input = [
        'campaignId' => $campaign->id, 'unitPrice' => '10000000000.00',
        'availableQuantity' => 1, 'minimumQuantity' => 1,
        'reservedUntil' => now()->addDays(3)->toIso8601String(),
        'transportIncluded' => true, 'transportUnitPrice' => '0',
        'deliveryDays' => 3, 'deliveryMode' => 'recolha',
        'validUntil' => now()->addDays(3)->toIso8601String(),
    ];

    $this->actingAs($supplierUser)->post(route('fornecedor.propostas.store'), $input)->assertSessionHasNoErrors();

    $proposal = Proposal::query()->where('campaign_id', $campaign->id)->sole();
    expect($proposal->unit_price)->toBe('10000000000.00');
    $this->actingAs($campaign->organizer)->post(route('organizador.campanhas.seleccionar', [$campaign, $proposal]))->assertSessionHasErrors('amount');
    expect($campaign->fresh()->status)->toBe('em_avaliacao')
        ->and($proposal->fresh()->status)->toBe('submetida');
});
