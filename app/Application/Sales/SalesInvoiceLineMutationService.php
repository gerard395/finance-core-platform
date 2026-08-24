<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesTaxSnapshot;
use InvalidArgumentException;

final readonly class SalesInvoiceLineMutationService
{
    public function __construct(private SalesTaxCodeResolver $taxCodes, private SalesInvoiceReadinessChecker $readiness, private SalesInvoiceMutationService $mutations) {}

    public function add(AdministrationId $administrationId, SalesInvoiceId $invoiceId, SalesInvoiceLineInput $input): SalesInvoiceWriteResult
    {
        return $this->mutate($administrationId, $invoiceId, $input, true);
    }

    public function update(AdministrationId $administrationId, SalesInvoiceId $invoiceId, SalesInvoiceLineInput $input): SalesInvoiceWriteResult
    {
        return $this->mutate($administrationId, $invoiceId, $input, false);
    }

    private function mutate(AdministrationId $administrationId, SalesInvoiceId $invoiceId, SalesInvoiceLineInput $input, bool $add): SalesInvoiceWriteResult
    {
        $resolution = $this->taxCodes->resolve($administrationId, $input->taxCodeId());
        $failure = CreateSalesInvoice::taxFailure($resolution->status());
        if ($failure !== null) {
            return $failure;
        }
        $taxCode = $resolution->taxCode();
        if ($taxCode === null) {
            return SalesInvoiceWriteResult::TaxCodeNotFound;
        }
        try {
            $line = new SalesInvoiceLine($input->id(), $input->description(), $input->quantity(), $input->unitPrice(), SalesTaxSnapshot::fromTaxCode($taxCode));
        } catch (InvalidArgumentException) {
            return SalesInvoiceWriteResult::TaxCalculationFailure;
        }

        return $this->mutations->mutate($administrationId, $invoiceId, function (SalesInvoice $invoice) use ($line, $add): ?SalesInvoiceWriteResult {
            if ($invoice->sourceOrderId() !== null) {
                return SalesInvoiceWriteResult::InvalidState;
            }
            $add ? $invoice->addLine($line) : $invoice->updateLine($line);

            return CreateSalesInvoice::readinessFailure($this->readiness->check($invoice)->status());
        });
    }
}
