<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;

interface BankPostingIdentityGenerator
{
    public function posting(): BankTransactionPostingId;

    public function journalEntry(): JournalEntryId;

    public function line(): JournalEntryLineId;

    public function settlement(): OpenItemSettlementId;
}
