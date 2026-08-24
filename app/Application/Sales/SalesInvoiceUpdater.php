<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesInvoice;

interface SalesInvoiceUpdater
{
    public function update(AdministrationId $administrationId, SalesInvoice $invoice): SalesInvoiceWriteResult;
}
