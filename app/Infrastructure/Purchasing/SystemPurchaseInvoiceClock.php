<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseInvoiceClock;
use DateTimeImmutable;

final class SystemPurchaseInvoiceClock implements PurchaseInvoiceClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
