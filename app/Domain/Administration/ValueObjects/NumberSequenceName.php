<?php

declare(strict_types=1);

namespace App\Domain\Administration\ValueObjects;

use InvalidArgumentException;

final readonly class NumberSequenceName
{
    public function __construct(
        private string $value,
    ) {
        if (preg_match('/\A\s|\s\z/u', $value) === 1 || mb_strlen($value) < 2 || mb_strlen($value) > 255) {
            throw new InvalidArgumentException('Number sequence name must contain 2 to 255 characters without surrounding whitespace.');
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
