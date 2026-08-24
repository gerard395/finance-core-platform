<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxTreatment;
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
            if (in_array($snapshot->treatment(), [TaxTreatment::ReverseChargeEuService, TaxTreatment::IntraCommunityGoods], true)) {
                $customer = $invoice->customerFiscalSnapshot();
                $supplier = $invoice->supplierFiscalSnapshot();
                if ($customer?->vatIdentificationNumber() === null) {
                    return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::CustomerVatIdMissing);
                }
                if ($customer->fiscalJurisdiction() === null) {
                    return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::CustomerJurisdictionMissing);
                }
                if ($supplier?->vatIdentificationNumber() === null) {
                    return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::SupplierVatIdMissing);
                }
                if ($supplier->fiscalJurisdiction() === null) {
                    return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::SupplierJurisdictionMissing);
                }
                if ($invoice->supplyDate() === null) {
                    return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::SupplyDateMissing);
                }
                $expectedIcp = $snapshot->treatment() === TaxTreatment::ReverseChargeEuService ? IcpClassification::Service : IcpClassification::GoodsSupply;
                if ($snapshot->icpClassification() !== $expectedIcp) {
                    return new SalesInvoiceReadiness(SalesInvoiceReadinessStatus::TaxCalculationFailed);
                }
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
