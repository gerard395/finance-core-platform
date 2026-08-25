<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;

final readonly class PurchaseSupplierSnapshot
{
    public function __construct(
        public SupplierId $supplierId,
        public RelationId $relationId,
        public SupplierNumber $supplierNumber,
        public DisplayName $name,
        public ?VatIdentificationNumber $vatIdentificationNumber,
        public ?CountryCode $fiscalJurisdiction,
    ) {}
}
