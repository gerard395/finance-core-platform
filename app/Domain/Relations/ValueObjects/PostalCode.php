<?php

declare(strict_types=1);

namespace App\Domain\Relations\ValueObjects;

use InvalidArgumentException;

final readonly class PostalCode
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9 -]{0,14}[A-Za-z0-9])?\z/', $value) !== 1) {
            throw new InvalidArgumentException('Postal code must contain 2 to 16 valid ASCII characters.');
        }

        if (strlen($value) < 2) {
            throw new InvalidArgumentException('Postal code must contain 2 to 16 valid ASCII characters.');
        }
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
