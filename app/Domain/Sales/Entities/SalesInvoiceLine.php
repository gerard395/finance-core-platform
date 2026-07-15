<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Sales\ValueObjects\LineDescription;
use App\Domain\Sales\ValueObjects\Quantity;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class SalesInvoiceLine
{
    private Money $lineTotal;

    public function __construct(
        private SalesInvoiceLineId $id,
        private LineDescription $description,
        private Quantity $quantity,
        private Money $unitPrice,
    ) {
        if (str_starts_with($unitPrice->amount(), '-')) {
            throw new InvalidArgumentException('Unit price cannot be negative.');
        }

        $this->lineTotal = new Money(
            self::multiply($quantity->value(), $unitPrice->amount()),
            $unitPrice->currency(),
        );
    }

    public function id(): SalesInvoiceLineId
    {
        return $this->id;
    }

    public function description(): LineDescription
    {
        return $this->description;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function lineTotal(): Money
    {
        return $this->lineTotal;
    }

    private static function multiply(string $left, string $right): string
    {
        [$leftDigits, $leftScale] = self::digitsAndScale($left);
        [$rightDigits, $rightScale] = self::digitsAndScale($right);
        $digits = self::multiplyDigits($leftDigits, $rightDigits);
        $scale = $leftScale + $rightScale;

        if ($scale > 0) {
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
            $digits = rtrim(rtrim($digits, '0'), '.');
        }

        $decimalLength = str_contains($digits, '.') ? strlen(substr(strrchr($digits, '.'), 1)) : 0;

        if ($decimalLength > 8) {
            throw new InvalidArgumentException('Line total exceeds Money decimal precision without rounding.');
        }

        $normalized = ltrim($digits, '0');

        if ($normalized === '') {
            return '0';
        }

        return str_starts_with($normalized, '.') ? '0'.$normalized : $normalized;
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
}
