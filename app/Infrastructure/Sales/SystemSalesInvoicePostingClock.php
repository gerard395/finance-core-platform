<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesInvoicePostingClock;
use DateTimeImmutable;

final class SystemSalesInvoicePostingClock implements SalesInvoicePostingClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
