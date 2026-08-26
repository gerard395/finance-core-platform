<?php

declare(strict_types=1);

namespace App\Application\Banking;

use DateTimeImmutable;

interface BankTransactionClock
{
    public function now(): DateTimeImmutable;
}
