<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankEntryReconciliationClock;
use DateTimeImmutable;

final class SystemBankEntryReconciliationClock implements BankEntryReconciliationClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
