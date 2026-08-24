<?php

declare(strict_types=1);

namespace App\Domain\Shared\Fiscal;

use InvalidArgumentException;

final readonly class VatIdentificationNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (strlen($normalized) < 2 || strlen($normalized) > 32) {
            throw new InvalidArgumentException('VAT identification number must contain 2 to 32 characters.');
        }

        if (preg_match('/\A[A-Z0-9][A-Z0-9.-]*\z/', $normalized) !== 1) {
            throw new InvalidArgumentException('VAT identification number contains invalid characters.');
        }

        $this->value = $normalized;
    }

    public function toString(): string
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
