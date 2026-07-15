<?php

declare(strict_types=1);

namespace App\Domain\Administration\ValueObjects;

use InvalidArgumentException;

final readonly class DecimalPrecision
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 0 || $value > 8) {
            throw new InvalidArgumentException('Decimal precision must be between 0 and 8.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
