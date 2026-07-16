<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Services;

use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\ValueObjects\TaxCalculationResult;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Finance\Money;
use DomainException;

final class TaxCalculation
{
    public function calculate(Money $netAmount, TaxCode $taxCode): TaxCalculationResult
    {
        if (! $taxCode->isActive()) {
            throw new DomainException('Tax calculation requires an active tax code.');
        }

        $taxAmount = $netAmount->multiply(self::percentageMultiplier($taxCode->rate()));
        $grossAmount = self::add($netAmount, $taxAmount);

        return new TaxCalculationResult(
            $netAmount,
            $taxAmount,
            $grossAmount,
            $taxCode->id(),
            $taxCode->rate(),
        );
    }

    private static function percentageMultiplier(TaxRate $rate): string
    {
        [$whole, $fraction] = array_pad(explode('.', $rate->value(), 2), 2, '');
        $digits = ltrim($whole.$fraction, '0') ?: '0';
        $scale = strlen($fraction) + 2;
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $multiplier = substr($digits, 0, -$scale).'.'.substr($digits, -$scale);

        return rtrim(rtrim($multiplier, '0'), '.');
    }

    private static function add(Money $left, Money $right): Money
    {
        $negative = str_starts_with($left->amount(), '-');
        $leftDigits = self::scaledAmount($left);
        $rightDigits = self::scaledAmount($right);
        $sum = self::addUnsignedIntegers($leftDigits, $rightDigits);
        $amount = self::decimalAmount($sum);

        return new Money($negative && $amount !== '0' ? '-'.$amount : $amount, $left->currency());
    }

    private static function scaledAmount(Money $amount): string
    {
        $value = str_starts_with($amount->amount(), '-') ? substr($amount->amount(), 1) : $amount->amount();
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ltrim($whole.str_pad($fraction, 8, '0'), '0') ?: '0';
    }

    private static function addUnsignedIntegers(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $sum = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $digit = $carry;
            $digit += $leftIndex >= 0 ? (int) $left[$leftIndex--] : 0;
            $digit += $rightIndex >= 0 ? (int) $right[$rightIndex--] : 0;
            $sum = ($digit % 10).$sum;
            $carry = intdiv($digit, 10);
        }

        return ltrim($sum, '0') ?: '0';
    }

    private static function decimalAmount(string $scaledAmount): string
    {
        $digits = str_pad($scaledAmount, 9, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -8);
        $fraction = rtrim(substr($digits, -8), '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }
}
