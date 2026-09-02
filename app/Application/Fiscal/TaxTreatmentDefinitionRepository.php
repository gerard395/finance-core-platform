<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;

interface TaxTreatmentDefinitionRepository
{
    public function append(TaxTreatmentDefinition $definition): void;

    public function findVersion(
        AdministrationId $administrationId,
        TaxTreatmentDefinitionId $definitionId,
        int $version,
    ): ?TaxTreatmentDefinition;

    public function findActiveForTaxCode(
        AdministrationId $administrationId,
        TaxCodeId $taxCodeId,
    ): ?TaxTreatmentDefinition;

    public function resolveActiveForTaxCode(
        AdministrationId $administrationId,
        TaxCodeId $taxCodeId,
    ): TaxTreatmentDefinitionSelection;
}
