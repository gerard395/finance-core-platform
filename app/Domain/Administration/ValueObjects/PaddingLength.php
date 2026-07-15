<?php

declare(strict_types=1);

namespace App\Domain\Administration\ValueObjects;

use InvalidArgumentException;

final readonly class PaddingLength
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 0 || $value > 32) {
            throw new InvalidArgumentException('Padding length must be between 0 and 32.');
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
