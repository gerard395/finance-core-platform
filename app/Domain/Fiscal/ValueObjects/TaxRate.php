<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use InvalidArgumentException;

final readonly class TaxRate
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/\A(?:0|[1-9][0-9]{0,2})(?:\.[0-9]{1,4})?\z/', $value) !== 1) {
            throw new InvalidArgumentException('Tax rate must be a decimal string with at most 4 decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        if ((int) $whole > 100 || ((int) $whole === 100 && trim($fraction, '0') !== '')) {
            throw new InvalidArgumentException('Tax rate must be between 0.0000 and 100.0000 percent.');
        }

        $normalized = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
