<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;

final readonly class BankEntryFinancialSummary
{
    /** @param list<BankEntryFinancialAllocationSummary> $allocations */
    public function __construct(public BankEntryReconciliationId $reconciliationId, public BankTransactionId $bankTransactionId, public BankEntryReconciliationIntent $intent, public PostingDate $postingDate, public JournalEntryId $journalEntryId, public array $allocations, public ?LedgerAccountId $otherContraAccountId, public ?BankTransactionReversalId $reversalId) {}
}
