<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class JournalEntryLine
{
    public function __construct(
        private JournalEntryLineId $id,
        private LedgerAccountId $ledgerAccountId,
        private ?Money $debit,
        private ?Money $credit,
        private string $description,
    ) {
        if (($debit === null) === ($credit === null)) {
            throw new DomainException('A journal entry line must contain exactly one debit or credit amount.');
        }

        if (($debit !== null && str_starts_with($debit->amount(), '-'))
            || ($credit !== null && str_starts_with($credit->amount(), '-'))) {
            throw new DomainException('Journal entry line amounts cannot be negative.');
        }
    }

    public function id(): JournalEntryLineId
    {
        return $this->id;
    }

    public function ledgerAccountId(): LedgerAccountId
    {
        return $this->ledgerAccountId;
    }

    public function debit(): ?Money
    {
        return $this->debit;
    }

    public function credit(): ?Money
    {
        return $this->credit;
    }

    public function description(): string
    {
        return $this->description;
    }
}
