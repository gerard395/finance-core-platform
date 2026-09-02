<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLedgerAccountRole;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxLegDefinition;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\TaxTreatmentDefinitionRecord;
use DomainException;

final class EloquentTaxTreatmentDefinitionRepository implements TaxTreatmentDefinitionRepository
{
    public function append(TaxTreatmentDefinition $definition): void
    {
        if (TaxTreatmentDefinitionRecord::query()->where('id', $definition->id()->toString())->where('version', $definition->version())->exists()) {
            throw new DomainException('This tax treatment definition version already exists.');
        }

        TaxTreatmentDefinitionRecord::query()->create([
            'id' => $definition->id()->toString(),
            'administration_id' => $definition->administrationId()->toString(),
            'tax_code_id' => $definition->taxCodeId()->toString(),
            'version' => $definition->version(),
            'treatment_type' => $definition->treatmentType()->value,
            'jurisdiction' => $definition->jurisdiction(),
            'vat_rate' => $definition->vatRate()->value(),
            'supplier_vat_mode' => $definition->supplierVatMode()->value,
            'deductibility_policy' => $definition->deductibilityPolicy()->value,
            'leg_definitions' => array_map(static fn (TaxLegDefinition $leg): array => [
                'role' => $leg->role->value,
                'direction' => $leg->direction->value,
                'reporting_classification' => $leg->reportingClassification->value,
                'ledger_account_role' => $leg->ledgerAccountRole->value,
                'emit_when_zero' => $leg->emitWhenZero,
            ], $definition->legDefinitions()),
            'active' => $definition->active(),
            'effective_from' => $definition->effectiveFrom(),
        ]);
    }

    public function findVersion(AdministrationId $administrationId, TaxTreatmentDefinitionId $definitionId, int $version): ?TaxTreatmentDefinition
    {
        $record = TaxTreatmentDefinitionRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('id', $definitionId->toString())
            ->where('version', $version)
            ->first();

        return $record === null ? null : self::hydrate($record);
    }

    public function findActiveForTaxCode(AdministrationId $administrationId, TaxCodeId $taxCodeId): ?TaxTreatmentDefinition
    {
        $record = TaxTreatmentDefinitionRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('tax_code_id', $taxCodeId->toString())
            ->where('active', true)
            ->orderByDesc('version')
            ->first();

        return $record === null ? null : self::hydrate($record);
    }

    private static function hydrate(TaxTreatmentDefinitionRecord $record): TaxTreatmentDefinition
    {
        $legs = array_map(static fn (array $leg): TaxLegDefinition => new TaxLegDefinition(
            TaxLegRole::from($leg['role']),
            TaxPostingDirection::from($leg['direction']),
            TaxReportingClassification::from($leg['reporting_classification']),
            TaxLedgerAccountRole::from($leg['ledger_account_role']),
            (bool) $leg['emit_when_zero'],
        ), $record->getAttribute('leg_definitions'));

        return new TaxTreatmentDefinition(
            new TaxTreatmentDefinitionId(new Uuid($record->getAttribute('id'))),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new TaxCodeId(new Uuid($record->getAttribute('tax_code_id'))),
            (int) $record->getAttribute('version'),
            TaxTreatmentType::from($record->getAttribute('treatment_type')),
            $record->getAttribute('jurisdiction'),
            new TaxRate((string) $record->getAttribute('vat_rate')),
            SupplierVatMode::from($record->getAttribute('supplier_vat_mode')),
            DeductibilityPolicy::from($record->getAttribute('deductibility_policy')),
            $legs,
            (bool) $record->getAttribute('active'),
            $record->getAttribute('effective_from'),
        );
    }
}
