<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseInvoicePostingClock;
use DateTimeImmutable;

final class SystemPurchaseInvoicePostingClock implements PurchaseInvoicePostingClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
