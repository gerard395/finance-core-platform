<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

interface SalesCreditSourceReader
{
    public function read(AdministrationId $administrationId, SalesInvoiceId $sourceInvoiceId, ?SalesCreditInvoiceId $currentCreditInvoiceId = null): SalesCreditSource;
}
