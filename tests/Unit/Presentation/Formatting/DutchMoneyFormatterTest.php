<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Formatting;

use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Presentation\Formatting\DutchMoneyFormatter;
use PHPUnit\Framework\TestCase;

final class DutchMoneyFormatterTest extends TestCase
{
    public function test_formats_exact_money_without_float_conversion_or_rounding(): void
    {
        $formatter = new DutchMoneyFormatter;
        $currency = new Currency('EUR');

        self::assertSame('EUR 0,00', $formatter->format(Money::zero($currency)));
        self::assertSame('EUR 1.234,50', $formatter->format(new Money('1234.5', $currency)));
        self::assertSame('EUR -1.234,12345678', $formatter->format(new Money('-1234.12345678', $currency)));
    }
}
