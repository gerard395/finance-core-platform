<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankTransactionClock;
use DateTimeImmutable;

final class SystemBankTransactionClock implements BankTransactionClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
