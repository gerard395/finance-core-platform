<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesCreditInvoice;

interface SalesCreditInvoiceCreator
{
    public function create(AdministrationId $administrationId, SalesCreditInvoice $invoice): SalesCreditInvoiceWriteResult;
}
