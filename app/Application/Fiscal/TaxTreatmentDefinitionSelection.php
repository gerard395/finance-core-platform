<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;

final readonly class TaxTreatmentDefinitionSelection
{
    private function __construct(public TaxTreatmentDefinitionSelectionStatus $status, public ?TaxTreatmentDefinition $definition = null) {}

    public static function found(TaxTreatmentDefinition $definition): self
    {
        return new self(TaxTreatmentDefinitionSelectionStatus::Found, $definition);
    }

    public static function missing(): self
    {
        return new self(TaxTreatmentDefinitionSelectionStatus::Missing);
    }

    public static function integrityFailure(): self
    {
        return new self(TaxTreatmentDefinitionSelectionStatus::IntegrityFailure);
    }
}
