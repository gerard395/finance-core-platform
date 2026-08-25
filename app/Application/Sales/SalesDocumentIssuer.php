<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class SalesDocumentIssuer
{
    public function __construct(
        public ?string $legalName,
        public ?string $displayName,
        public ?AddressLine $addressLine1,
        public ?AddressLine $addressLine2,
        public ?PostalCode $postalCode,
        public ?City $city,
        public ?CountryCode $countryCode,
        public ?VatIdentificationNumber $vatIdentificationNumber,
        public ?CountryCode $fiscalJurisdiction,
        public ?string $registrationNumber,
        public ?EmailAddress $businessEmail,
        public ?string $businessPhone,
        public ?string $website,
        public ?Iban $iban,
        public ?Bic $bic,
        public ?string $accountHolder,
    ) {}
}
