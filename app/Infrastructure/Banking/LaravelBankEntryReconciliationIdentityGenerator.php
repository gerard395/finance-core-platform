<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankEntryReconciliationIdentityGenerator;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelBankEntryReconciliationIdentityGenerator implements BankEntryReconciliationIdentityGenerator
{
    public function historyId(): BankEntryReconciliationHistoryId
    {
        return new BankEntryReconciliationHistoryId(new Uuid((string) Str::uuid()));
    }
}
