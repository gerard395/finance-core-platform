<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Purchasing\Enums\PurchaseSupplyClassification;
use InvalidArgumentException;

final readonly class InternationalPurchaseSourceFacts
{
    public function __construct(
        public string $supplierJurisdiction,
        public string $customerJurisdiction,
        public ?string $supplierVatIdentity,
        public ?string $customerVatIdentity,
        public PurchaseSupplyClassification $classification,
        public bool $businessToBusiness,
        public bool $arrivesInNetherlands,
        public bool $generalRuleConfirmed,
        public bool $specialPlaceOfSupply,
        public bool $foreignSupplierVat,
        public bool $importOrCustoms,
        public ?string $evidence,
        public ?string $deductibilityRationale,
        public string $deductibilityPolicyVersion = 'IPV-V1',
    ) {
        foreach ([$supplierJurisdiction, $customerJurisdiction] as $country) {
            if (preg_match('/\A[A-Z]{2}\z/', $country) !== 1) {
                throw new InvalidArgumentException('International purchase jurisdictions must be ISO alpha-2 country codes.');
            }
        }
        if (trim($deductibilityPolicyVersion) === '') {
            throw new InvalidArgumentException('Deductibility policy version is required.');
        }
    }
}
