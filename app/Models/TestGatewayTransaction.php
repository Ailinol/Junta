<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TestGatewayTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property positive-int $id
 * @property string $reference
 * @property string $kind
 * @property string $status
 * @property int $amount_cents
 * @property string $currency
 * @property CarbonInterface|null $settled_at
 */
#[Fillable(['reference', 'kind', 'status', 'amount_cents', 'currency', 'settled_at'])]
class TestGatewayTransaction extends Model
{
    /** @use HasFactory<TestGatewayTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'settled_at' => 'datetime'];
    }
}
