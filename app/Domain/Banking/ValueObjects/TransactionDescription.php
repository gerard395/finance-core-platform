<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use InvalidArgumentException;

final readonly class TransactionDescription
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A\s|\s\z/u', $value) === 1) {
            throw new InvalidArgumentException('Transaction description cannot contain leading or trailing whitespace.');
        }

        $length = mb_strlen($value);

        if ($length < 1 || $length > 1000) {
            throw new InvalidArgumentException('Transaction description must contain 1 to 1000 characters.');
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
