<?php

declare(strict_types=1);

namespace App\Domain\Relations\ValueObjects;

use InvalidArgumentException;

final readonly class Bic
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/\A[A-Za-z]{6}[A-Za-z0-9]{2}(?:[A-Za-z0-9]{3})?\z/', $value) !== 1) {
            throw new InvalidArgumentException('BIC must contain 8 or 11 valid ASCII characters.');
        }

        $this->value = strtoupper($value);
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
