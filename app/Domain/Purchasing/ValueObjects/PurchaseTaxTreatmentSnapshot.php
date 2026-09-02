<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Fiscal\ValueObjects\TaxLegDefinition;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PurchaseTaxTreatmentSnapshot
{
    /** @param list<TaxLegDefinition> $legDefinitions */
    public function __construct(
        public TaxTreatmentDefinitionId $definitionId,
        public int $definitionVersion,
        public TaxTreatmentType $treatmentType,
        public string $jurisdiction,
        public TaxRate $vatRate,
        public SupplierVatMode $supplierVatMode,
        public DeductibilityPolicy $deductibilityPolicy,
        public array $legDefinitions,
        public DeductibilityBasisPoints $deductibility,
        public InternationalPurchaseSourceFacts $sourceFacts,
        public UserId $frozenBy,
        public DateTimeImmutable $frozenAt,
    ) {
        if ($definitionVersion < 1 || $legDefinitions === []) {
            throw new InvalidArgumentException('International purchase treatment snapshot is incomplete.');
        }
    }
}
