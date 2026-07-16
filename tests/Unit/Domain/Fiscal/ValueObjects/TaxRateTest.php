<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\ValueObjects\TaxRate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxRateTest extends TestCase
{
    #[DataProvider('validRates')]
    public function test_it_accepts_and_normalizes_decimal_string_percentages(string $input, string $expected): void
    {
        $rate = new TaxRate($input);

        self::assertSame($expected, $rate->value());
        self::assertSame($expected, $rate->toString());
        self::assertSame($expected, (string) $rate);
    }

    /** @return array<string, array{string, string}> */
    public static function validRates(): array
    {
        return [
            'minimum' => ['0.0000', '0'],
            'fraction' => ['9.5000', '9.5'],
            'four decimals' => ['21.1234', '21.1234'],
            'maximum' => ['100.0000', '100'],
        ];
    }

    #[DataProvider('invalidRates')]
    public function test_it_rejects_invalid_rates(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaxRate($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidRates(): array
    {
        return [
            'negative' => ['-0.0001'],
            'above maximum' => ['100.0001'],
            'too many decimals' => ['21.12345'],
            'float notation' => ['2.1e1'],
            'leading zero' => ['021.00'],
        ];
    }

    public function test_equality_uses_the_normalized_value(): void
    {
        self::assertTrue(new TaxRate('21.0000')->equals(new TaxRate('21')));
        self::assertFalse(new TaxRate('21')->equals(new TaxRate('9')));
    }
}
