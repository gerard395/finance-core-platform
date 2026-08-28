<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use DateTimeImmutable;

interface PurchaseCreditClock
{
    public function now(): DateTimeImmutable;
}
