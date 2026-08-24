<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

interface SalesInvoiceReadRepository
{
    public function findForAdministration(AdministrationId $administrationId, SalesInvoiceId $invoiceId): ?SalesInvoice;
}
