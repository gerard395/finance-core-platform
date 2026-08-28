<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

use InvalidArgumentException;

final readonly class BankTransactionReversalReason
{
    public function __construct(private string $value)
    {
        if ($value !== trim($value)) {
            throw new InvalidArgumentException('Bank transaction reversal reason cannot contain leading or trailing whitespace.');
        }
        if (mb_strlen($value) < 1 || mb_strlen($value) > 500) {
            throw new InvalidArgumentException('Bank transaction reversal reason must contain 1 to 500 characters.');
        }
    }

    public static function fromUserInput(string $value): self
    {
        return new self(trim($value));
    }

    public function value(): string
    {
        return $this->value;
    }
}
