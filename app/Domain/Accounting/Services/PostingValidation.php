<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\ValueObjects\ValidationError;
use App\Domain\Accounting\ValueObjects\ValidationResult;
use App\Domain\Shared\Finance\Money;

final class PostingValidation
{
    public function validate(PostingRequest $request): ValidationResult
    {
        $errors = [];
        $lines = $request->lines();

        if (count($lines) < 2) {
            $errors[] = new ValidationError('minimum_lines', 'A posting request must contain at least two journal entry lines.');
        }

        $debitTotal = '0';
        $creditTotal = '0';
        $currency = null;
        $lineIds = [];

        foreach ($lines as $line) {
            $lineId = $line->id()->toString();

            if (isset($lineIds[$lineId])) {
                $errors[] = new ValidationError('duplicate_line_id', 'Journal entry line identities must be unique.');
            }

            $lineIds[$lineId] = true;
            $money = $line->debit() ?? $line->credit();

            if ($money === null) {
                continue;
            }

            if (str_starts_with($money->amount(), '-')) {
                $errors[] = new ValidationError('negative_amount', 'Journal entry line amounts cannot be negative.');

                continue;
            }

            if ($currency === null) {
                $currency = $money->currency();
            } elseif (! $currency->equals($money->currency())) {
                $errors[] = new ValidationError('currency_mismatch', 'All journal entry lines must use the same currency.');
            }

            if ($line->debit() !== null) {
                $debitTotal = self::addAmounts($debitTotal, $money);
            } else {
                $creditTotal = self::addAmounts($creditTotal, $money);
            }
        }

        if ($debitTotal !== $creditTotal) {
            $errors[] = new ValidationError('unbalanced_entry', 'Total debit must equal total credit.');
        }

        return new ValidationResult($errors);
    }

    private static function addAmounts(string $total, Money $money): string
    {
        return self::addUnsignedIntegers($total, self::scaledAmount($money));
    }

    private static function scaledAmount(Money $money): string
    {
        [$whole, $fraction] = array_pad(explode('.', $money->amount(), 2), 2, '');

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
}
