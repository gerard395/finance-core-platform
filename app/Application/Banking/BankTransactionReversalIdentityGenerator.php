<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\BankTransactionSettlementReversalLinkId;

interface BankTransactionReversalIdentityGenerator
{
    public function reversal(): BankTransactionReversalId;

    public function journalEntry(): JournalEntryId;

    public function line(): JournalEntryLineId;

    public function settlement(): OpenItemSettlementId;

    public function settlementLink(): BankTransactionSettlementReversalLinkId;
}
