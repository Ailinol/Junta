<?php

namespace Database\Factories;

use App\Models\PlatformPricingSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPricingSetting>
 */
class PlatformPricingSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commission_bps' => 250,
            'updated_by' => null,
        ];
    }
}
