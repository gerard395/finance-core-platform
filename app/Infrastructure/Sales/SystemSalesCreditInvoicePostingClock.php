<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesCreditInvoicePostingClock;
use DateTimeImmutable;

final class SystemSalesCreditInvoicePostingClock implements SalesCreditInvoicePostingClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
