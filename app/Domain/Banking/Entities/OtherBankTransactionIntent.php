<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class OtherBankTransactionIntent
{
    public function __construct(private LedgerAccountId $contraLedgerAccountId, private Money $amount)
    {
        if (! $amount->isPositive() || $amount->currency()->code() !== 'EUR') {
            throw new DomainException('Other bank intent requires positive EUR Money.');
        }
    }

    public function contraLedgerAccountId(): LedgerAccountId
    {
        return $this->contraLedgerAccountId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }
}
