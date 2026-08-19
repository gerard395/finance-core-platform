<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class TrialBalanceResult
{
    /**
     * @param  list<TrialBalanceLine>  $lines
     */
    public function __construct(
        private array $lines,
        private Money $totalDebit,
        private Money $totalCredit,
    ) {
        $currency = $this->totalDebit->currency();

        if (! $currency->equals($this->totalCredit->currency())) {
            throw new DomainException('A trial balance result must use one currency.');
        }

        foreach ($this->lines as $line) {
            if (! $currency->equals($line->totalDebit()->currency())
                || ! $currency->equals($line->totalCredit()->currency())
                || ! $currency->equals($line->balance()->currency())) {
                throw new DomainException('A trial balance result must use one currency.');
            }
        }
    }

    /** @return list<TrialBalanceLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalDebit(): Money
    {
        return $this->totalDebit;
    }

    public function totalCredit(): Money
    {
        return $this->totalCredit;
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit->equals($this->totalCredit);
    }
}
