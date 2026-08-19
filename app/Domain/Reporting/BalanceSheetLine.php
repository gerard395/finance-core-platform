<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Shared\Finance\Money;

final readonly class BalanceSheetLine
{
    public function __construct(
        private LedgerAccountId $ledgerAccountId,
        private LedgerAccountType $ledgerAccountType,
        private Money $balance,
    ) {}

    public function ledgerAccountId(): LedgerAccountId
    {
        return $this->ledgerAccountId;
    }

    public function ledgerAccountType(): LedgerAccountType
    {
        return $this->ledgerAccountType;
    }

    public function balance(): Money
    {
        return $this->balance;
    }
}
