<?php

namespace App\Support;

class PlatformFeeCalculator
{
    public static function breakdown(float|int|string $grossAmount, ?float $percentage = null): array
    {
        $grossAmount = round((float) $grossAmount, 2);
        $percentage ??= (float) config('payments.platform_fee_percentage', 10);
        $percentage = round($percentage, 2);
        $platformFeeAmount = round($grossAmount * ($percentage / 100), 2);
        $netAmount = round($grossAmount - $platformFeeAmount, 2);

        return [
            'gross_amount' => $grossAmount,
            'platform_fee_percentage' => $percentage,
            'platform_fee_amount' => $platformFeeAmount,
            'net_amount' => $netAmount,
        ];
    }
}
