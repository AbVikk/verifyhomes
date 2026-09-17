<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class RentalPeriod
{
    public static function daysForMonths(int $months, ?Carbon $startsAt = null): int
    {
        $startsAt ??= now();

        return $startsAt->copy()->startOfDay()->diffInDays(
            $startsAt->copy()->startOfDay()->addMonthsNoOverflow(max(1, $months)),
        );
    }

    public static function label(?int $days, ?int $months = null): string
    {
        if (! $days || $days < 1) {
            return '-';
        }

        $suffix = match ($months) {
            12 => ' (1 year)',
            24 => ' (2 years)',
            default => $months && $months > 0 ? " ({$months} month".($months === 1 ? ')' : 's)') : '',
        };

        return "{$days} day".($days === 1 ? '' : 's').$suffix;
    }
}
