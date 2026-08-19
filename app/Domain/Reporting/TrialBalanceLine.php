<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Shared\Finance\Money;

final readonly class TrialBalanceLine
{
    public function __construct(
        private LedgerAccountId $ledgerAccountId,
        private Money $totalDebit,
        private Money $totalCredit,
        private Money $balance,
    ) {}

    public function ledgerAccountId(): LedgerAccountId
    {
        return $this->ledgerAccountId;
    }

    public function totalDebit(): Money
    {
        return $this->totalDebit;
    }

    public function totalCredit(): Money
    {
        return $this->totalCredit;
    }

    public function balance(): Money
    {
        // Trial Balance truth remains debit minus credit. Balance Sheet presentation
        // normalizes Liability and Equity balances with Money::absolute().
        return $this->balance;
    }
}
