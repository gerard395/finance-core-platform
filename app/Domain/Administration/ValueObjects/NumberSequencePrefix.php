<?php

declare(strict_types=1);

namespace App\Domain\Administration\ValueObjects;

use InvalidArgumentException;

final readonly class NumberSequencePrefix
{
    public function __construct(
        private string $value,
    ) {
        if (mb_strlen($value) > 32) {
            throw new InvalidArgumentException('Number sequence prefix cannot exceed 32 characters.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
