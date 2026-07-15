<?php

declare(strict_types=1);

namespace App\Domain\Administration\ValueObjects;

use InvalidArgumentException;

final readonly class AccountingSettings
{
    private AccountingNumberFormat $numberFormat;

    public function __construct(
        private string $defaultLanguage,
        private string $dateFormat,
        string $numberFormat,
        private DecimalPrecision $decimalPrecision,
        private string $timezone,
    ) {
        if (preg_match('/\A[a-z]{2}\z/', $defaultLanguage) !== 1) {
            throw new InvalidArgumentException('Default language must contain exactly two lowercase ASCII letters.');
        }

        if ($dateFormat === '' || mb_strlen($dateFormat) > 32) {
            throw new InvalidArgumentException('Date format must contain 1 to 32 characters.');
        }

        if (preg_match('/\A\s|\s\z/u', $dateFormat) === 1) {
            throw new InvalidArgumentException('Date format cannot contain leading or trailing whitespace.');
        }

        $this->numberFormat = AccountingNumberFormat::tryFrom($numberFormat)
            ?? throw new InvalidArgumentException('Unsupported accounting number format.');

        if (preg_match('/\A\s|\s\z/u', $timezone) === 1) {
            throw new InvalidArgumentException('Timezone cannot contain leading or trailing whitespace.');
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Unknown timezone.');
        }
    }

    public function defaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    public function dateFormat(): string
    {
        return $this->dateFormat;
    }

    public function numberFormat(): AccountingNumberFormat
    {
        return $this->numberFormat;
    }

    public function decimalPrecision(): DecimalPrecision
    {
        return $this->decimalPrecision;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
