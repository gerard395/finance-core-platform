<?php

declare(strict_types=1);

namespace App\Domain\Shared\Finance;

use DomainException;
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

    public function isPositive(): bool
    {
        return ! $this->isZero() && ! $this->isNegative();
    }

    public function isNegative(): bool
    {
        return str_starts_with($this->amount, '-');
    }

    public function multiply(string $multiplier): self
    {
        if (preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/', $multiplier) !== 1) {
            throw new InvalidArgumentException('Money multiplier must be a valid non-negative decimal string with at most 8 decimal places.');
        }

        $negative = str_starts_with($this->amount, '-');
        $amount = $negative ? substr($this->amount, 1) : $this->amount;
        [$amountDigits, $amountScale] = self::digitsAndScale($amount);
        [$multiplierDigits, $multiplierScale] = self::digitsAndScale($multiplier);
        $digits = self::multiplyDigits($amountDigits, $multiplierDigits);
        $scale = $amountScale + $multiplierScale;

        if ($scale > 0) {
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
            $digits = rtrim(rtrim($digits, '0'), '.');
        }

        $decimalLength = str_contains($digits, '.') ? strlen(substr(strrchr($digits, '.'), 1)) : 0;

        if ($decimalLength > 8) {
            throw new InvalidArgumentException('Money multiplication exceeds decimal precision without rounding.');
        }

        $normalized = ltrim($digits, '0');

        if ($normalized === '') {
            return self::zero($this->currency);
        }

        $normalized = str_starts_with($normalized, '.') ? '0'.$normalized : $normalized;

        return new self($negative ? '-'.$normalized : $normalized, $this->currency);
    }

    public function add(self $other): self
    {
        if (! $this->currency->equals($other->currency)) {
            throw new DomainException('Money amounts must use the same currency.');
        }

        $leftNegative = str_starts_with($this->amount, '-');
        $rightNegative = str_starts_with($other->amount, '-');
        $left = self::scaledAmount($this->amount);
        $right = self::scaledAmount($other->amount);

        if ($leftNegative === $rightNegative) {
            $amount = self::addDigits($left, $right);
            $negative = $leftNegative;
        } else {
            $comparison = self::compareUnsigned($left, $right);

            if ($comparison === 0) {
                return self::zero($this->currency);
            }

            $amount = $comparison > 0
                ? self::subtractDigits($left, $right)
                : self::subtractDigits($right, $left);
            $negative = $comparison > 0 ? $leftNegative : $rightNegative;
        }

        $decimal = self::decimalAmount($amount);

        return new self($negative ? '-'.$decimal : $decimal, $this->currency);
    }

    public function absolute(): self
    {
        return new self(str_starts_with($this->amount, '-') ? substr($this->amount, 1) : $this->amount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    /** @return array{string, int} */
    private static function digitsAndScale(string $value): array
    {
        $position = strpos($value, '.');

        return [$position === false ? $value : substr($value, 0, $position).substr($value, $position + 1), $position === false ? 0 : strlen($value) - $position - 1];
    }

    private static function multiplyDigits(string $left, string $right): string
    {
        $result = array_fill(0, strlen($left) + strlen($right), 0);

        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            for ($j = strlen($right) - 1; $j >= 0; $j--) {
                $position = $i + $j + 1;
                $value = ((int) $left[$i] * (int) $right[$j]) + $result[$position];
                $result[$position] = $value % 10;
                $result[$position - 1] += intdiv($value, 10);
            }
        }

        return ltrim(implode('', $result), '0') ?: '0';
    }

    private static function scaledAmount(string $amount): string
    {
        $unsigned = str_starts_with($amount, '-') ? substr($amount, 1) : $amount;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        return ltrim($whole.str_pad($fraction, 8, '0'), '0') ?: '0';
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

    private static function compareUnsigned(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return $left <=> $right;
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

    private static function decimalAmount(string $scaledAmount): string
    {
        $digits = str_pad($scaledAmount, 9, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -8);
        $fraction = rtrim(substr($digits, -8), '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }
}
