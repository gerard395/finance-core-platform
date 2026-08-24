<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;

interface SalesCreditInvoiceDetailReadRepository
{
    public function find(AdministrationId $administrationId, SalesCreditInvoiceId $id): ?SalesCreditInvoiceDetail;
}
