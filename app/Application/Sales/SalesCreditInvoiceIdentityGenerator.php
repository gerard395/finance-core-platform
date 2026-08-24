<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;

interface SalesCreditInvoiceIdentityGenerator
{
    public function creditInvoiceId(): SalesCreditInvoiceId;
}
