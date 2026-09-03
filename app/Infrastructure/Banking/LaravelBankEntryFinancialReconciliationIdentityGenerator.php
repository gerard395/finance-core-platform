<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankEntryFinancialReconciliationIdentityGenerator;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelBankEntryFinancialReconciliationIdentityGenerator implements BankEntryFinancialReconciliationIdentityGenerator
{
    public function reconciliationId(): BankEntryReconciliationId
    {
        return new BankEntryReconciliationId(new Uuid((string) Str::uuid()));
    }
}
