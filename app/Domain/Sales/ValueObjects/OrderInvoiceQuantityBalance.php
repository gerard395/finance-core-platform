<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use DomainException;
use InvalidArgumentException;

final readonly class OrderInvoiceQuantityBalance
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/', $value) !== 1) {
            throw new InvalidArgumentException('Quantity balance must be a non-negative decimal with at most 8 decimal places.');
        }
        $this->value = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public static function fromQuantity(Quantity $quantity): self
    {
        return new self($quantity->value());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isZero(): bool
    {
        return $this->value === '0';
    }

    public function add(self|Quantity $other): self
    {
        return new self(self::decimal(self::addDigits(self::scaled($this->value), self::scaled($other instanceof Quantity ? $other->value() : $other->value))));
    }

    public function subtract(self|Quantity $other): self
    {
        $left = self::scaled($this->value);
        $right = self::scaled($other instanceof Quantity ? $other->value() : $other->value);
        if (self::compare($left, $right) < 0) {
            throw new DomainException('Quantity balance cannot become negative.');
        }

        return new self(self::decimal(self::subtractDigits($left, $right)));
    }

    public function isLessThan(Quantity|self $other): bool
    {
        return self::compare(self::scaled($this->value), self::scaled($other instanceof Quantity ? $other->value() : $other->value)) < 0;
    }

    private static function scaled(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ltrim($whole.str_pad($fraction, 8, '0'), '0') ?: '0';
    }

    private static function decimal(string $scaled): string
    {
        $digits = str_pad($scaled, 9, '0', STR_PAD_LEFT);
        $fraction = rtrim(substr($digits, -8), '0');

        return $fraction === '' ? substr($digits, 0, -8) : substr($digits, 0, -8).'.'.$fraction;
    }

    private static function compare(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        return strlen($left) === strlen($right) ? $left <=> $right : strlen($left) <=> strlen($right);
    }

    private static function addDigits(string $left, string $right): string
    {
        $length = max(strlen($left), strlen($right));
        $left = str_pad($left, $length, '0', STR_PAD_LEFT);
        $right = str_pad($right, $length, '0', STR_PAD_LEFT);
        $carry = 0;
        $result = '';
        for ($index = $length - 1; $index >= 0; $index--) {
            $sum = (int) $left[$index] + (int) $right[$index] + $carry;
            $result = ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return ($carry > 0 ? $carry : '').$result;
    }

    private static function subtractDigits(string $left, string $right): string
    {
        $right = str_pad($right, strlen($left), '0', STR_PAD_LEFT);
        $borrow = 0;
        $result = '';
        for ($index = strlen($left) - 1; $index >= 0; $index--) {
            $digit = (int) $left[$index] - (int) $right[$index] - $borrow;
            $borrow = $digit < 0 ? 1 : 0;
            $result = ($digit < 0 ? $digit + 10 : $digit).$result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
