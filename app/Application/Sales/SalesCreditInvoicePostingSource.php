<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;

interface SalesCreditInvoicePostingSource
{
    public function findLockedForAdministration(AdministrationId $administrationId, SalesCreditInvoiceId $creditInvoiceId): ?SalesCreditInvoice;
}
