<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\AccountingNumberFormat;
use App\Domain\Administration\ValueObjects\AccountingSettings;
use App\Domain\Administration\ValueObjects\DecimalPrecision;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AccountingSettingsTest extends TestCase
{
    public function test_it_is_constructed_with_all_values_readable(): void
    {
        $precision = new DecimalPrecision(2);
        $settings = $this->createSettings(decimalPrecision: $precision);

        self::assertSame('nl', $settings->defaultLanguage());
        self::assertSame('d-m-Y', $settings->dateFormat());
        self::assertSame(AccountingNumberFormat::Dutch, $settings->numberFormat());
        self::assertSame($precision, $settings->decimalPrecision());
        self::assertSame('Europe/Amsterdam', $settings->timezone());
    }

    public function test_it_accepts_dutch_as_default_language(): void
    {
        self::assertSame('nl', $this->createSettings(defaultLanguage: 'nl')->defaultLanguage());
    }

    public function test_it_rejects_an_invalid_language(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createSettings(defaultLanguage: 'nld');
    }

    public function test_it_rejects_an_uppercase_language(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createSettings(defaultLanguage: 'NL');
    }

    public function test_it_rejects_an_empty_date_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createSettings(dateFormat: '');
    }

    public function test_it_rejects_whitespace_around_the_date_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createSettings(dateFormat: ' d-m-Y');
    }

    public function test_it_accepts_dutch_and_english_number_formats(): void
    {
        self::assertSame(AccountingNumberFormat::Dutch, $this->createSettings(numberFormat: 'nl')->numberFormat());
        self::assertSame(AccountingNumberFormat::English, $this->createSettings(numberFormat: 'en')->numberFormat());
    }

    public function test_it_rejects_an_unknown_number_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createSettings(numberFormat: 'de');
    }

    public function test_it_accepts_a_valid_timezone(): void
    {
        self::assertSame('Europe/Amsterdam', $this->createSettings()->timezone());
    }

    public function test_it_rejects_an_invalid_timezone(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createSettings(timezone: 'Europe/Invalid');
    }

    public function test_decimal_precision_remains_the_same_value_object(): void
    {
        $precision = new DecimalPrecision(4);

        self::assertSame($precision, $this->createSettings(decimalPrecision: $precision)->decimalPrecision());
    }

    public function test_it_is_immutable(): void
    {
        self::assertTrue(new ReflectionClass(AccountingSettings::class)->isReadOnly());
    }

    private function createSettings(
        string $defaultLanguage = 'nl',
        string $dateFormat = 'd-m-Y',
        string $numberFormat = 'nl',
        ?DecimalPrecision $decimalPrecision = null,
        string $timezone = 'Europe/Amsterdam',
    ): AccountingSettings {
        return new AccountingSettings(
            $defaultLanguage,
            $dateFormat,
            $numberFormat,
            $decimalPrecision ?? new DecimalPrecision(2),
            $timezone,
        );
    }
}
