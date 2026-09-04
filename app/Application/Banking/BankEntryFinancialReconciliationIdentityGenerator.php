<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;

interface BankEntryFinancialReconciliationIdentityGenerator
{
    public function reconciliationId(): BankEntryReconciliationId;
}
