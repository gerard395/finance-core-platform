<?php

declare(strict_types=1);

namespace App\Domain\Relations\ValueObjects;

use InvalidArgumentException;

final readonly class EmailAddress
{
    private string $value;

    public function __construct(string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email address must be valid.');
        }

        $this->value = strtolower($value);
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
