<?php

namespace App\Support;

class Currency
{
    public static function format(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        $numericAmount = (float) ($amount ?? 0);

        return static::symbol($currency).number_format($numericAmount, 2);
    }

    public static function formatCompact(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        $numericAmount = (float) ($amount ?? 0);
        $absoluteAmount = abs($numericAmount);

        if ($absoluteAmount < 100000) {
            return static::format($numericAmount, $currency);
        }

        foreach ([1000000000 => 'B', 1000000 => 'M', 1000 => 'K'] as $divisor => $suffix) {
            if ($absoluteAmount >= $divisor) {
                $value = $numericAmount / $divisor;
                $precision = abs($value) >= 100 ? 0 : (abs($value) >= 10 ? 1 : 2);
                $formattedValue = number_format($value, $precision, '.', '');
                if (str_contains($formattedValue, '.')) {
                    $formattedValue = rtrim(rtrim($formattedValue, '0'), '.');
                }

                return static::symbol($currency).$formattedValue.$suffix;
            }
        }

        return static::format($numericAmount, $currency);
    }

    public static function symbol(string $currency = 'NGN'): string
    {
        return $currency === 'NGN' ? "\u{20A6}" : $currency.' ';
    }
}
