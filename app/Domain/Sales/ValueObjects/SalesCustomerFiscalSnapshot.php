<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class SalesCustomerFiscalSnapshot
{
    public function __construct(
        private RelationId $relationId,
        private ?VatIdentificationNumber $vatIdentificationNumber,
        private ?CountryCode $fiscalJurisdiction,
    ) {}

    public function relationId(): RelationId
    {
        return $this->relationId;
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
