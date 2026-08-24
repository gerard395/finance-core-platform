<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

interface SalesInvoiceDetailReadRepository
{
    public function find(AdministrationId $administrationId, SalesInvoiceId $invoiceId): ?SalesInvoiceDetail;
}
