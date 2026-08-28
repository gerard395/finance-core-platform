<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseCreditClock;
use DateTimeImmutable;

final class SystemPurchaseCreditClock implements PurchaseCreditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
