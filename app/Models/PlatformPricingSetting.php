<?php

namespace App\Models;

use Database\Factories\PlatformPricingSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $commission_bps
 * @property int|null $updated_by
 */
#[Fillable(['commission_bps', 'updated_by'])]
class PlatformPricingSetting extends Model
{
    /** @use HasFactory<PlatformPricingSettingFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['commission_bps' => 'integer', 'updated_by' => 'integer'];
    }
}
