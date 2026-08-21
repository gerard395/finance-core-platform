<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesInvoice;

interface SalesInvoiceCreator
{
    public function create(AdministrationId $administrationId, SalesInvoice $invoice): SalesInvoiceWriteResult;
}
