<?php

namespace Tests\Unit;

use App\Support\Currency;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    #[Test]
    public function it_keeps_small_summary_amounts_exact(): void
    {
        $this->assertSame('₦5,000.00', Currency::formatCompact(5000));
        $this->assertSame('₦99,999.00', Currency::formatCompact(99999));
    }

    #[Test]
    public function it_compacts_large_summary_amounts_without_affecting_exact_formatting(): void
    {
        $this->assertSame('₦395K', Currency::formatCompact(395000));
        $this->assertSame('₦1.25M', Currency::formatCompact(1250000));
        $this->assertSame('₦12.5M', Currency::formatCompact(12450000));
        $this->assertSame('₦125M', Currency::formatCompact(125000000));
        $this->assertSame('₦1.25B', Currency::formatCompact(1250000000));
        $this->assertSame('₦1,250,000,000.00', Currency::format(1250000000));
    }

    #[Test]
    public function it_preserves_significant_zeroes_and_compacts_all_boundaries(): void
    {
        $naira = "\u{20A6}";

        foreach ([
            0 => $naira.'0.00', 900 => $naira.'900.00', 999 => $naira.'999.00',
            1000 => $naira.'1,000.00', 5000 => $naira.'5,000.00', 10000 => $naira.'10,000.00',
            39000 => $naira.'39,000.00', 99999 => $naira.'99,999.00', 100000 => $naira.'100K',
            120000 => $naira.'120K', 390000 => $naira.'390K',
            500000 => $naira.'500K', 999000 => $naira.'999K', 1000000 => $naira.'1M',
            1010000 => $naira.'1.01M', 10000000 => $naira.'10M', 12500000 => $naira.'12.5M',
            100000000 => $naira.'100M', 125000000 => $naira.'125M', 1000000000 => $naira.'1B',
            1250000000 => $naira.'1.25B',
        ] as $amount => $expected) {
            $this->assertSame($expected, Currency::formatCompact($amount));
        }
    }
}
