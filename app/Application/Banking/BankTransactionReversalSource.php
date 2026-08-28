<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\BankTransactionReversal;

final readonly class BankTransactionReversalSource
{
    /** @param list<BankTransactionReversalSettlementSource> $settlements */
    public function __construct(
        public BankTransaction $transaction,
        public ?BankTransactionPosting $posting,
        public ?JournalEntry $journalEntry,
        public array $settlements,
        public ?BankTransactionReversal $reversal,
        public bool $financialGraphCoherent,
    ) {}
}
