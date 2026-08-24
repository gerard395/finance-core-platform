<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class CancelSalesInvoice
{
    public function __construct(private SalesInvoiceMutationService $mutations, private SalesInvoiceReadRepository $reader, private OrderDerivedSalesInvoiceLifecycle $orderLifecycle) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId): SalesInvoiceWriteResult
    {
        $sourceOrderId = $this->reader->findForAdministration($administrationId, $invoiceId)?->sourceOrderId();
        if ($sourceOrderId !== null) {
            return $this->orderLifecycle->cancel($administrationId, $invoiceId, $sourceOrderId);
        }

        return $this->mutations->mutate($administrationId, $invoiceId, static function ($invoice): ?SalesInvoiceWriteResult {
            $invoice->cancel();

            return null;
        });
    }
}
