<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesCreditInvoice;

interface SalesCreditInvoiceUpdater
{
    public function update(AdministrationId $administrationId, SalesCreditInvoice $invoice): SalesCreditInvoiceWriteResult;
}
