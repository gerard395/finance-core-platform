<?php

declare(strict_types=1);

namespace App\Domain\Accounting\ValueObjects;

use InvalidArgumentException;

final readonly class JournalEntryReference
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A\s|\s\z/u', $value) === 1) {
            throw new InvalidArgumentException('Journal entry reference cannot contain leading or trailing whitespace.');
        }

        $length = mb_strlen($value);

        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('Journal entry reference must contain 1 to 255 characters.');
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
