<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class SalesSupplierFiscalSnapshot
{
    public function __construct(
        private AdministrationId $administrationId,
        private ?VatIdentificationNumber $vatIdentificationNumber,
        private ?CountryCode $fiscalJurisdiction,
    ) {}

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function vatIdentificationNumber(): ?VatIdentificationNumber
    {
        return $this->vatIdentificationNumber;
    }

    public function fiscalJurisdiction(): ?CountryCode
    {
        return $this->fiscalJurisdiction;
    }
}
