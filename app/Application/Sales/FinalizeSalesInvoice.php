<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;

final readonly class FinalizeSalesInvoice
{
    public function __construct(private SalesInvoiceReadinessChecker $readiness, private SalesInvoiceMutationService $mutations, private SalesInvoiceReadRepository $reader, private OrderDerivedSalesInvoiceLifecycle $orderLifecycle) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId): SalesInvoiceWriteResult
    {
        $sourceOrderId = $this->reader->findForAdministration($administrationId, $invoiceId)?->sourceOrderId();
        if ($sourceOrderId !== null) {
            return $this->orderLifecycle->finalize($administrationId, $invoiceId, $sourceOrderId);
        }

        return $this->mutations->mutate($administrationId, $invoiceId, function ($invoice): ?SalesInvoiceWriteResult {
            $readiness = $this->readiness->check($invoice);
            if ($readiness->status() !== SalesInvoiceReadinessStatus::Ready) {
                return $readiness->status() === SalesInvoiceReadinessStatus::TaxCalculationFailed
                    ? SalesInvoiceWriteResult::TaxCalculationFailure
                    : SalesInvoiceWriteResult::InvalidState;
            }
            $invoice->finalize();

            return null;
        });
    }
}
