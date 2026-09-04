<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\Entities\BankTransactionReversal;
use App\Domain\Banking\Enums\BankTransactionIntentType;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class BankTransactionReversalReadiness
{
    public function __construct(
        public BankTransactionReversalEligibilityStatus $status,
        public BankTransactionId $bankTransactionId,
        public BankTransactionIntentType $intentType,
        public ?PaymentType $type,
        public Money $signedAmount,
        public ?RelationId $relationId,
        public ?LedgerAccountId $contraAccountId,
        public TransactionDescription $description,
        public DateTimeImmutable $transactionDate,
        public ?PostingDate $originalPostingDate,
        public ?JournalEntryId $originalJournalEntryId,
        public int $allocationCount,
        public int $settlementCount,
        public int $reversedSettlementCount,
        public ?BankTransactionReversalId $reversalId,
        public ?BankTransactionReversal $reversal,
    ) {}
}
