<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\Services\SalesFiscalWordingPolicy;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;

final readonly class SalesDocumentRenderModelBuilder
{
    public function __construct(
        private QuotationReadRepository $quotations,
        private SalesInvoiceReadRepository $invoices,
        private SalesCreditInvoiceReadRepository $credits,
        private SalesDocumentIssuerReader $issuers,
        private SalesDocumentIssuerReadiness $issuerReadiness,
        private TaxCalculation $taxCalculation,
        private SalesFiscalWordingPolicy $wording,
    ) {}

    public function build(AdministrationId $administrationId, SalesDocumentSource $source): SalesDocumentRenderModel|PrepareSalesDocumentArtifactStatus
    {
        $purpose = match ($source->type) {
            SalesDocumentType::Quotation => SalesDocumentRecipientPurpose::Quotation,
            SalesDocumentType::SalesInvoice => SalesDocumentRecipientPurpose::SalesInvoice,
            SalesDocumentType::SalesCreditInvoice => SalesDocumentRecipientPurpose::SalesCreditInvoice,
        };
        $readiness = $this->issuerReadiness->assess($purpose, $administrationId);
        if ($readiness !== SalesDocumentIssuerReadinessStatus::Success) {
            return $readiness === SalesDocumentIssuerReadinessStatus::MissingPaymentData
                ? PrepareSalesDocumentArtifactStatus::MissingPaymentData
                : PrepareSalesDocumentArtifactStatus::MissingIssuerData;
        }
        $issuer = $this->issuers->readIssuer($administrationId);
        if ($issuer === null) {
            return PrepareSalesDocumentArtifactStatus::MissingIssuerData;
        }

        return match ($source->type) {
            SalesDocumentType::Quotation => $this->quotation($administrationId, $source, $issuer),
            SalesDocumentType::SalesInvoice => $this->invoice($administrationId, $source, $issuer),
            SalesDocumentType::SalesCreditInvoice => $this->credit($administrationId, $source, $issuer),
        };
    }

    private function quotation(AdministrationId $admin, SalesDocumentSource $source, SalesDocumentIssuer $issuer): SalesDocumentRenderModel|PrepareSalesDocumentArtifactStatus
    {
        $document = $this->quotations->findForAdministration($admin, new QuotationId(new Uuid($source->id)));
        if ($document === null) {
            return PrepareSalesDocumentArtifactStatus::NotFound;
        }
        if ($document->documentAddressSnapshot() === null) {
            return PrepareSalesDocumentArtifactStatus::MissingDocumentAddress;
        }
        $customer = $document->customerSnapshot();
        if ($customer === null || $document->lines() === []) {
            return PrepareSalesDocumentArtifactStatus::InvalidSource;
        }

        return new SalesDocumentRenderModel($source->type, $source->id, $document->number()->value(), 'quotation-v1', [
            'document' => ['number' => $document->number()->value(), 'date' => $document->quotationDate()->format('Y-m-d'), 'valid_until' => $document->expiryDate()?->format('Y-m-d'), 'currency' => $document->currency()->code()],
            'customer' => ['number' => $customer->customerNumber()->toString(), 'name' => $customer->displayName()->toString(), 'address' => $this->address($document->documentAddressSnapshot())],
            'issuer' => $this->issuer($issuer, false),
            'lines' => array_map(static fn ($line) => ['description' => $line->description()->value(), 'quantity' => $line->quantity()->value(), 'unit_price' => $line->unitPrice()->amount(), 'net' => $line->lineTotal()->amount()], $document->lines()),
            'totals' => ['net' => $document->total()->amount()],
        ]);
    }

    private function invoice(AdministrationId $admin, SalesDocumentSource $source, SalesDocumentIssuer $issuer): SalesDocumentRenderModel|PrepareSalesDocumentArtifactStatus
    {
        $invoice = $this->invoices->findForAdministration($admin, new SalesInvoiceId(new Uuid($source->id)));
        if ($invoice === null) {
            return PrepareSalesDocumentArtifactStatus::NotFound;
        }

        return $this->fiscalModel($invoice, $source, $issuer, false);
    }

    private function credit(AdministrationId $admin, SalesDocumentSource $source, SalesDocumentIssuer $issuer): SalesDocumentRenderModel|PrepareSalesDocumentArtifactStatus
    {
        $credit = $this->credits->findForAdministration($admin, new SalesCreditInvoiceId(new Uuid($source->id)));
        if ($credit === null) {
            return PrepareSalesDocumentArtifactStatus::NotFound;
        }
        $sourceInvoice = $this->invoices->findForAdministration($admin, $credit->sourceInvoiceId());
        if ($sourceInvoice === null) {
            return PrepareSalesDocumentArtifactStatus::InvalidSource;
        }

        return $this->fiscalModel($credit, $source, $issuer, true, $sourceInvoice);
    }

    private function fiscalModel(SalesInvoice|SalesCreditInvoice $document, SalesDocumentSource $source, SalesDocumentIssuer $issuer, bool $credit, ?SalesInvoice $sourceInvoice = null): SalesDocumentRenderModel|PrepareSalesDocumentArtifactStatus
    {
        $customer = $document->customerSnapshot();
        $address = $document->invoiceAddressSnapshot();
        $customerFiscal = $document->customerFiscalSnapshot();
        $supplierFiscal = $document->supplierFiscalSnapshot();
        if ($customer === null || $address === null || $customerFiscal === null || $supplierFiscal === null || $document->lines() === []) {
            return PrepareSalesDocumentArtifactStatus::InvalidSource;
        }
        $taxLines = $credit ? $sourceInvoice?->lines() ?? [] : $document->lines();
        if (count($taxLines) !== count($document->lines())) {
            return PrepareSalesDocumentArtifactStatus::InvalidSource;
        }
        $net = Money::zero($document->currency());
        $tax = Money::zero($document->currency());
        $gross = Money::zero($document->currency());
        $summary = [];
        $lines = [];
        foreach ($document->lines() as $index => $line) {
            $snapshot = $taxLines[$index]->taxSnapshot();
            if ($snapshot === null) {
                return PrepareSalesDocumentArtifactStatus::InvalidSource;
            }
            $calculation = $this->taxCalculation->calculate($line->lineTotal(), $snapshot->forCalculation());
            $net = $net->add($calculation->netAmount());
            $tax = $tax->add($calculation->taxAmount());
            $gross = $gross->add($calculation->grossAmount());
            $key = $snapshot->treatment()->value.'|'.$snapshot->taxRate()->value().'|'.$snapshot->vatReturnClassification()->value;
            $summary[$key] ??= ['treatment' => $snapshot->treatment()->value, 'rate' => $snapshot->taxRate()->value(), 'vat_classification' => $snapshot->vatReturnClassification()->value, 'wording' => $this->wording->forTreatment($snapshot->treatment())->value, 'net' => Money::zero($document->currency()), 'tax' => Money::zero($document->currency())];
            $summary[$key]['net'] = $summary[$key]['net']->add($calculation->netAmount());
            $summary[$key]['tax'] = $summary[$key]['tax']->add($calculation->taxAmount());
            $lines[] = ['description' => $line->description()->value(), 'quantity' => $line->quantity()->value(), 'unit_price' => $line->unitPrice()->amount(), 'net' => $calculation->netAmount()->amount(), 'tax' => $calculation->taxAmount()->amount(), 'gross' => $calculation->grossAmount()->amount(), 'treatment' => $snapshot->treatment()->value, 'rate' => $snapshot->taxRate()->value(), 'wording' => $this->wording->forTreatment($snapshot->treatment())->value];
        }
        $summary = array_values(array_map(static fn (array $row) => [...$row, 'net' => $row['net']->amount(), 'tax' => $row['tax']->amount()], $summary));
        $number = $credit ? $document->number()->value() : $document->number()->value();
        $date = $credit ? $document->creditInvoiceDate()->format('Y-m-d') : $document->invoiceDate()->format('Y-m-d');
        $supplyDate = $credit ? $document->originalSupplyDate()?->value()->format('Y-m-d') : $document->supplyDate()?->value()->format('Y-m-d');

        return new SalesDocumentRenderModel($source->type, $source->id, $number, $credit ? 'sales-credit-invoice-v1' : 'sales-invoice-v1', [
            'document' => ['number' => $number, 'date' => $date, 'due_date' => $credit ? null : $document->dueDate()->format('Y-m-d'), 'supply_date' => $supplyDate, 'currency' => $document->currency()->code(), 'source_invoice_number' => $credit ? $sourceInvoice?->number()->value() : null],
            'customer' => ['number' => $customer->customerNumber()->toString(), 'name' => $customer->displayName()->toString(), 'address' => $this->address($address), 'vat_id' => $customerFiscal->vatIdentificationNumber()?->toString(), 'jurisdiction' => $customerFiscal->fiscalJurisdiction()?->value()],
            'supplier_fiscal' => ['vat_id' => $supplierFiscal->vatIdentificationNumber()?->toString(), 'jurisdiction' => $supplierFiscal->fiscalJurisdiction()?->value()],
            'issuer' => $this->issuer($issuer, true),
            'payment_reference' => $number,
            'lines' => $lines,
            'tax_summary' => $summary,
            'totals' => ['net' => $net->amount(), 'tax' => $tax->amount(), 'gross' => $gross->amount()],
        ]);
    }

    /** @return array<string, ?string> */
    private function address(SalesAddressSnapshot $address): array
    {
        return ['line_1' => $address->addressLine()->value(), 'line_2' => $address->addressLine2()?->value(), 'postal_code' => $address->postalCode()->value(), 'city' => $address->city()->value(), 'country' => $address->countryCode()->value()];
    }

    /** @return array<string, ?string> */
    private function issuer(SalesDocumentIssuer $issuer, bool $payment): array
    {
        return ['name' => $issuer->legalName ?? $issuer->displayName, 'display_name' => $issuer->displayName, 'line_1' => $issuer->addressLine1?->value(), 'line_2' => $issuer->addressLine2?->value(), 'postal_code' => $issuer->postalCode?->value(), 'city' => $issuer->city?->value(), 'country' => $issuer->countryCode?->value(), 'registration_number' => $issuer->registrationNumber, 'business_email' => $issuer->businessEmail?->value(), 'business_phone' => $issuer->businessPhone, 'website' => $issuer->website, 'iban' => $payment ? $issuer->iban?->value() : null, 'bic' => $payment ? $issuer->bic?->value() : null, 'account_holder' => $payment ? $issuer->accountHolder : null];
    }
}
