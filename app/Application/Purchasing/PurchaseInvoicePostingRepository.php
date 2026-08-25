<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

interface PurchaseInvoicePostingRepository
{
    public function findForInvoice(AdministrationId $administrationId, PurchaseInvoiceId $invoiceId): ?PurchaseInvoicePosting;

    public function append(PurchaseInvoicePosting $posting): bool;
}
