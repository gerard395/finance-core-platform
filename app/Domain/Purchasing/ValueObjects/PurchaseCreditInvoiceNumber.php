<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use InvalidArgumentException;

final readonly class PurchaseCreditInvoiceNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1 || mb_strlen($value) > 128) {
            throw new InvalidArgumentException('Supplier credit invoice number is invalid.');
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function canonical(): string
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
