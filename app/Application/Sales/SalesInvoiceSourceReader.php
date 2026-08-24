<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

interface SalesInvoiceSourceReader
{
    public function readPosted(
        AdministrationId $administrationId,
        SalesInvoiceId $salesInvoiceId,
    ): SalesInvoiceSource;
}
