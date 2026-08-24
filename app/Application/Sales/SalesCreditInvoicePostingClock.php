<?php

declare(strict_types=1);

namespace App\Application\Sales;

use DateTimeImmutable;

interface SalesCreditInvoicePostingClock
{
    public function now(): DateTimeImmutable;
}
