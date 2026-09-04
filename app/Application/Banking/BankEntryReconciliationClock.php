<?php

declare(strict_types=1);

namespace App\Application\Banking;

use DateTimeImmutable;

interface BankEntryReconciliationClock
{
    public function now(): DateTimeImmutable;
}
