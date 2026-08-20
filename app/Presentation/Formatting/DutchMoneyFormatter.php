<?php

declare(strict_types=1);

namespace App\Presentation\Formatting;

use App\Domain\Shared\Finance\Money;

final readonly class DutchMoneyFormatter
{
    public function format(Money $money): string
    {
        $amount = $money->amount();
        $negative = str_starts_with($amount, '-');
        $unsigned = $negative ? substr($amount, 1) : $amount;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        $decimals = str_pad($fraction, 2, '0');

        return $money->currency()->code().' '.($negative ? '-' : '').$grouped.','.$decimals;
    }
}
