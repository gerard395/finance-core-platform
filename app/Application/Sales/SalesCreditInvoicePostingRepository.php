<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;

interface SalesCreditInvoicePostingRepository
{
    public function findForCreditInvoice(AdministrationId $administrationId, SalesCreditInvoiceId $creditInvoiceId): ?SalesCreditInvoicePosting;

    public function append(SalesCreditInvoicePosting $posting): SalesCreditInvoicePostingAppendResult;
}
