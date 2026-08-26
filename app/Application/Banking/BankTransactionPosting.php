<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;

final readonly class BankTransactionPosting
{
    public function __construct(
        public BankTransactionPostingId $id,
        public AdministrationId $administrationId,
        public BankTransactionId $bankTransactionId,
        public JournalEntryId $journalEntryId,
        public PostingDate $postingDate,
    ) {}
}
