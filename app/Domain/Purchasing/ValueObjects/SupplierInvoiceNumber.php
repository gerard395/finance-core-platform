<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use InvalidArgumentException;

final readonly class SupplierInvoiceNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value) ?? $value;
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }
        if ($value === '' || mb_strlen($value) > 128 || preg_match('/\p{C}/u', $value) === 1) {
            throw new InvalidArgumentException('Supplier invoice number must contain 1 to 128 printable Unicode characters.');
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

    public function __toString(): string
    {
        return $this->value;
    }
}
