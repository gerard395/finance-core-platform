<?php

declare(strict_types=1);

namespace App\Domain\Identity\ValueObjects;

use InvalidArgumentException;

final readonly class PermissionCode
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/\A[A-Za-z0-9._]{2,64}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Permission code must contain 2 to 64 valid ASCII characters.');
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

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
