<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use InvalidArgumentException;

final readonly class Quantity
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/', $value) !== 1) {
            throw new InvalidArgumentException('Quantity must be a valid decimal string with at most 8 decimal places.');
        }

        $normalized = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        if ($normalized === '0') {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

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

    public function __toString(): string
    {
        return $this->value;
    }
}
