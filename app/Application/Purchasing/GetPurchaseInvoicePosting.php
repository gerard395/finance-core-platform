<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

final readonly class GetPurchaseInvoicePosting
{
    public function __construct(private PurchaseInvoicePostingRepository $postings) {}

    public function execute(AdministrationId $administrationId, PurchaseInvoiceId $invoiceId): ?PurchaseInvoicePosting
    {
        return $this->postings->findForInvoice($administrationId, $invoiceId);
    }
}
