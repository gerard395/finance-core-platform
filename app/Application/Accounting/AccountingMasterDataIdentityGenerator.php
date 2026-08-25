<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;

interface AccountingMasterDataIdentityGenerator
{
    public function journalId(): JournalId;

    public function ledgerAccountId(): LedgerAccountId;
}
