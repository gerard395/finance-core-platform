<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class SalesDocumentIssuerReadiness
{
    public function __construct(private SalesDocumentIssuerReader $issuers) {}

    public function assess(SalesDocumentRecipientPurpose $documentType, AdministrationId $administrationId): SalesDocumentIssuerReadinessStatus
    {
        $issuer = $this->issuers->readIssuer($administrationId);
        if ($issuer === null || ($issuer->legalName === null && $issuer->displayName === null)) {
            return SalesDocumentIssuerReadinessStatus::MissingIssuerName;
        }
        if ($issuer->addressLine1 === null || $issuer->postalCode === null || $issuer->city === null || $issuer->countryCode === null) {
            return SalesDocumentIssuerReadinessStatus::MissingAddress;
        }
        if ($issuer->registrationNumber === null) {
            return SalesDocumentIssuerReadinessStatus::MissingRegistrationNumber;
        }
        if ($documentType !== SalesDocumentRecipientPurpose::Quotation && ($issuer->iban === null || $issuer->accountHolder === null)) {
            return SalesDocumentIssuerReadinessStatus::MissingPaymentData;
        }

        return SalesDocumentIssuerReadinessStatus::Success;
    }
}
