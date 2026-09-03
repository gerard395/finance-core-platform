<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankImportClock;
use DateTimeImmutable;

final class SystemBankImportClock implements BankImportClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
