<?php

declare(strict_types=1);

namespace App\Infrastructure\Accounting;

use App\Application\Accounting\AccountingMasterDataIdentityGenerator;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelAccountingMasterDataIdentityGenerator implements AccountingMasterDataIdentityGenerator
{
    public function journalId(): JournalId
    {
        return new JournalId(new Uuid(Str::uuid()->toString()));
    }

    public function ledgerAccountId(): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid(Str::uuid()->toString()));
    }
}
