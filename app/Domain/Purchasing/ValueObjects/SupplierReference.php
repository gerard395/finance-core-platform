<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use InvalidArgumentException;

final readonly class SupplierReference
{
    public function __construct(private string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Supplier reference cannot be empty.');
        }

        if (preg_match('/\A\s|\s\z/u', $value) === 1) {
            throw new InvalidArgumentException('Supplier reference cannot contain leading or trailing whitespace.');
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

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
