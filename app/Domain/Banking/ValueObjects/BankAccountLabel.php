<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use InvalidArgumentException;

final readonly class BankAccountLabel
{
    public function __construct(private string $value)
    {
        if (trim($value) !== $value || mb_strlen($value) < 2 || mb_strlen($value) > 100) {
            throw new InvalidArgumentException('Bank account label must contain 2 to 100 characters without surrounding whitespace.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
