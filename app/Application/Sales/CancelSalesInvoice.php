<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class CancelSalesInvoice
{
    public function __construct(private SalesInvoiceMutationService $mutations) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId): SalesInvoiceWriteResult
    {
        return $this->mutations->mutate($administrationId, $invoiceId, static function ($invoice): ?SalesInvoiceWriteResult {
            if ($invoice->sourceOrderId() !== null) {
                return SalesInvoiceWriteResult::InvalidState;
            }
            $invoice->cancel();

            return null;
        });
    }
}
