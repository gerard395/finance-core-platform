<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Finance;

use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_constructor_exposes_amount_and_same_currency(): void
    {
        $currency = new Currency('EUR');
        $money = new Money('125.50', $currency);

        self::assertSame('125.5', $money->amount());
        self::assertSame($currency, $money->currency());
        self::assertFalse($money->isZero());
    }

    public function test_equality_uses_normalized_amount_and_currency(): void
    {
        $money = new Money('10.00', new Currency('EUR'));

        self::assertTrue($money->equals(new Money('10.0', new Currency('eur'))));
        self::assertFalse($money->equals(new Money('10.01', new Currency('EUR'))));
        self::assertFalse($money->equals(new Money('10.00', new Currency('USD'))));
    }

    public function test_zero_creates_canonical_zero_for_currency(): void
    {
        $currency = new Currency('EUR');
        $zero = Money::zero($currency);

        self::assertSame('0', $zero->amount());
        self::assertSame($currency, $zero->currency());
        self::assertTrue($zero->isZero());
        self::assertTrue($zero->equals(new Money('-0.000', new Currency('EUR'))));
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amount_is_rejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money($amount, new Currency('EUR'));
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'empty' => [''],
            'float notation' => ['1,50'],
            'leading zero' => ['01.50'],
            'trailing decimal point' => ['1.'],
            'more than eight decimals' => ['1.123456789'],
            'surrounding whitespace' => [' 1.50 '],
            'scientific notation' => ['1e2'],
        ];
    }
}
