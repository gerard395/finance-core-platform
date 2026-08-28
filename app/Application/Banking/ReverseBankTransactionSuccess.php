<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;

final readonly class ReverseBankTransactionSuccess
{
    /** @param list<ReversedOpenItemBalance> $openItemBalances */
    public function __construct(
        public BankTransactionReversalId $reversalId,
        public BankTransactionId $originalBankTransactionId,
        public JournalEntryId $originalJournalEntryId,
        public JournalEntryId $reversalJournalEntryId,
        public PostingDate $reversalPostingDate,
        public int $reversedSettlementCount,
        public array $openItemBalances,
    ) {}
}
