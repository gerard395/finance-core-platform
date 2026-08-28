<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankTransactionReversalIdentityGenerator;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\BankTransactionSettlementReversalLinkId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelBankTransactionReversalIdentityGenerator implements BankTransactionReversalIdentityGenerator
{
    public function reversal(): BankTransactionReversalId
    {
        return new BankTransactionReversalId($this->uuid());
    }

    public function journalEntry(): JournalEntryId
    {
        return new JournalEntryId($this->uuid());
    }

    public function line(): JournalEntryLineId
    {
        return new JournalEntryLineId($this->uuid());
    }

    public function settlement(): OpenItemSettlementId
    {
        return new OpenItemSettlementId($this->uuid());
    }

    public function settlementLink(): BankTransactionSettlementReversalLinkId
    {
        return new BankTransactionSettlementReversalLinkId($this->uuid());
    }

    private function uuid(): Uuid
    {
        return new Uuid((string) Str::uuid());
    }
}
