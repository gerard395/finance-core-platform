<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Shared\Finance\Money;

final readonly class GeneralLedgerLine
{
    public function __construct(
        private PostingDate $postingDate,
        private JournalEntryId $journalEntryId,
        private JournalEntryLineId $journalEntryLineId,
        private JournalId $journalId,
        private JournalEntryReference $reference,
        private LedgerAccountId $ledgerAccountId,
        private Money $debit,
        private Money $credit,
        private Money $runningBalance,
    ) {}

    public function postingDate(): PostingDate
    {
        return $this->postingDate;
    }

    public function journalEntryId(): JournalEntryId
    {
        return $this->journalEntryId;
    }

    public function journalEntryLineId(): JournalEntryLineId
    {
        return $this->journalEntryLineId;
    }

    public function journalId(): JournalId
    {
        return $this->journalId;
    }

    public function reference(): JournalEntryReference
    {
        return $this->reference;
    }

    public function ledgerAccountId(): LedgerAccountId
    {
        return $this->ledgerAccountId;
    }

    public function debit(): Money
    {
        return $this->debit;
    }

    public function credit(): Money
    {
        return $this->credit;
    }

    public function runningBalance(): Money
    {
        return $this->runningBalance;
    }
}
