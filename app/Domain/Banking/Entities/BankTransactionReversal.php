<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class BankTransactionReversal
{
    public function __construct(
        public BankTransactionReversalId $id,
        public AdministrationId $administrationId,
        public BankTransactionId $originalBankTransactionId,
        public BankTransactionPostingId $originalBankTransactionPostingId,
        public JournalEntryId $originalJournalEntryId,
        public JournalEntryId $reversalJournalEntryId,
        public PostingDate $reversalPostingDate,
        public BankTransactionReversalReason $reason,
        public UserId $reversedBy,
        public DateTimeImmutable $reversedAt,
    ) {}
}
