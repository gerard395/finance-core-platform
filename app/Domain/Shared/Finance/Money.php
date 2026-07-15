<?php

declare(strict_types=1);

namespace App\Domain\Shared\Finance;

use InvalidArgumentException;

final readonly class Money
{
    private string $amount;

    public function __construct(string $amount, private Currency $currency)
    {
        if (preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/', $amount) !== 1) {
            throw new InvalidArgumentException('Money amount must be a valid decimal string with at most 8 decimal places.');
        }

        $normalized = str_contains($amount, '.')
            ? rtrim(rtrim($amount, '0'), '.')
            : $amount;

        $this->amount = $normalized === '-0' ? '0' : $normalized;
    }

    public static function zero(Currency $currency): self
    {
        return new self('0', $currency);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function isZero(): bool
    {
        return $this->amount === '0';
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }
}
