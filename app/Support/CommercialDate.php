<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class CommercialDate
{
    /** Date-only inputs retain their historical UTC meaning; local clock inputs use Maputo. */
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value)) {
            return $value;
        }
        try {
            $date = CarbonImmutable::parse($value, 'Africa/Maputo');
            if ($date->format('Y-m-d\TH:i') !== str_replace(' ', 'T', substr($value, 0, 16))) {
                return $value;
            }

            return $date->utc()->toIso8601String();
        } catch (\Exception) {
            return $value;
        }
    }
}
