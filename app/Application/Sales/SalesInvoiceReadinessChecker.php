<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class SalesInvoiceReadinessChecker
{
    public function __construct(private TaxCalculation $taxCalculation) {}

    public function check(SalesInvoice $invoice): SalesInvoiceReadiness
    {
        if ($invoice->customerSnapshot() === null) {
            return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::MissingCustomerSnapshot);
        }
        if ($invoice->invoiceAddressSnapshot() === null) {
            return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::MissingInvoiceAddress);
        }
        if ($invoice->lines() === []) {
            return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::MissingLines);
        }

        $net = Money::zero($invoice->currency());
        $tax = Money::zero($invoice->currency());
        $gross = Money::zero($invoice->currency());
        foreach ($invoice->lines() as $line) {
            $snapshot = $line->taxSnapshot();
            if ($snapshot === null) {
                return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::MissingTaxSnapshot);
            }
            try {
                $calculation = $this->taxCalculation->calculate($line->lineTotal(), $snapshot->forCalculation());
            } catch (InvalidArgumentException) {
                return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::TaxCalculationFailed);
            }
            $net = $net->add($calculation->netAmount());
            $tax = $tax->add($calculation->taxAmount());
            $gross = $gross->add($calculation->grossAmount());
        }

        return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::Ready, $net, $tax, $gross);
    }
}
