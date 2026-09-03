<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;

interface BankEntryReconciliationIdentityGenerator
{
    public function historyId(): BankEntryReconciliationHistoryId;
}
