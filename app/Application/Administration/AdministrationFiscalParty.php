<?php

declare(strict_types=1);

namespace App\Application\Administration;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class AdministrationFiscalParty
{
    public function __construct(
        public AdministrationId $administrationId,
        public ?VatIdentificationNumber $vatIdentificationNumber,
        public ?CountryCode $fiscalJurisdiction,
    ) {}
}
