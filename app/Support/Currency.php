<?php

namespace App\Support;

class Currency
{
    public static function format(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        $numericAmount = (float) ($amount ?? 0);

        return static::symbol($currency).number_format($numericAmount, 2);
    }

    public static function symbol(string $currency = 'NGN'): string
    {
        return $currency === 'NGN' ? "\u{20A6}" : $currency.' ';
    }
}
