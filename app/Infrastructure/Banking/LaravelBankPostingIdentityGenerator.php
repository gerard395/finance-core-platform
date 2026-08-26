<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankPostingIdentityGenerator;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelBankPostingIdentityGenerator implements BankPostingIdentityGenerator
{
    private function u(): Uuid
    {
        return new Uuid((string) Str::uuid());
    }

    public function posting(): BankTransactionPostingId
    {
        return new BankTransactionPostingId($this->u());
    }

    public function journalEntry(): JournalEntryId
    {
        return new JournalEntryId($this->u());
    }

    public function line(): JournalEntryLineId
    {
        return new JournalEntryLineId($this->u());
    }

    public function settlement(): OpenItemSettlementId
    {
        return new OpenItemSettlementId($this->u());
    }
}
