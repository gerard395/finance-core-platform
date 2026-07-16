<?php

declare(strict_types=1);

namespace App\Domain\Accounting\ValueObjects;

use InvalidArgumentException;

final readonly class LedgerAccountCode
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/\A[A-Za-z0-9]{2,16}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Ledger account code must contain 2 to 16 ASCII letters or digits.');
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
