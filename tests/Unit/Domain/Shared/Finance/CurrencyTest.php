<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Finance;

use App\Domain\Shared\Finance\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function test_it_accepts_eur(): void
    {
        self::assertSame('EUR', new Currency('EUR')->code());
    }

    public function test_it_normalizes_lowercase_to_uppercase(): void
    {
        self::assertSame('EUR', new Currency('eur')->code());
    }

    public function test_it_normalizes_mixed_case_to_uppercase(): void
    {
        self::assertSame('EUR', new Currency('eUr')->code());
    }

    public function test_equal_currencies_compare_as_equal(): void
    {
        self::assertTrue(new Currency('EUR')->equals(new Currency('eur')));
    }

    public function test_different_currencies_compare_as_unequal(): void
    {
        self::assertFalse(new Currency('EUR')->equals(new Currency('USD')));
    }

    public function test_string_methods_return_the_same_code(): void
    {
        $currency = new Currency('eur');

        self::assertSame('EUR', $currency->code());
        self::assertSame('EUR', $currency->toString());
        self::assertSame('EUR', (string) $currency);
    }

    public function test_it_rejects_two_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('EU');
    }

    public function test_it_rejects_four_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('EURO');
    }

    public function test_it_rejects_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('EU1');
    }

    public function test_it_rejects_symbols(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('EU€');
    }

    public function test_it_rejects_leading_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency(' EUR');
    }

    public function test_it_rejects_trailing_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('EUR ');
    }

    public function test_it_rejects_empty_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('');
    }

    public function test_it_rejects_non_ascii_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Currency('EÜR');
    }
}
