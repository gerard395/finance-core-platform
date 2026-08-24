<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

interface SalesInvoicePostingRepository
{
    public function findForInvoice(AdministrationId $administrationId, SalesInvoiceId $salesInvoiceId): ?SalesInvoicePosting;

    public function append(SalesInvoicePosting $posting): SalesInvoicePostingAppendResult;
}
