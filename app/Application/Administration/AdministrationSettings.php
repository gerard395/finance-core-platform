<?php

declare(strict_types=1);

namespace App\Application\Administration;

use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class AdministrationSettings
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?VatIdentificationNumber $vatIdentificationNumber,
        public ?CountryCode $fiscalJurisdiction,
    ) {}
}
