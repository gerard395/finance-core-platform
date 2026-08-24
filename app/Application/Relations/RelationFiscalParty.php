<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class RelationFiscalParty
{
    public function __construct(
        public RelationId $relationId,
        public ?VatIdentificationNumber $vatIdentificationNumber,
        public ?CountryCode $fiscalJurisdiction,
    ) {}
}
