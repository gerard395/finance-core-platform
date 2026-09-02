<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxLegDefinition;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TaxTreatmentDefinition
{
    /** @param list<TaxLegDefinition> $legDefinitions */
    public function __construct(
        private TaxTreatmentDefinitionId $id,
        private AdministrationId $administrationId,
        private TaxCodeId $taxCodeId,
        private int $version,
        private TaxTreatmentType $treatmentType,
        private string $jurisdiction,
        private TaxRate $vatRate,
        private SupplierVatMode $supplierVatMode,
        private DeductibilityPolicy $deductibilityPolicy,
        private array $legDefinitions,
        private bool $active = true,
        private ?DateTimeImmutable $effectiveFrom = null,
    ) {
        if ($version < 1) {
            throw new InvalidArgumentException('Tax treatment definition version must be positive.');
        }

        if (preg_match('/\A[A-Z]{2}\z/', $jurisdiction) !== 1) {
            throw new InvalidArgumentException('Tax treatment jurisdiction must be an ISO alpha-2 country code.');
        }

        $roles = [];
        foreach ($legDefinitions as $definition) {
            if (isset($roles[$definition->role->value])) {
                throw new InvalidArgumentException('A tax treatment definition can define each leg role only once.');
            }
            $roles[$definition->role->value] = true;
        }

        if ($supplierVatMode === SupplierVatMode::SelfAssessed && ! isset($roles[TaxLegRole::VatPayable->value])) {
            throw new InvalidArgumentException('A self-assessed treatment requires a VAT payable leg definition.');
        }
    }

    public function id(): TaxTreatmentDefinitionId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function taxCodeId(): TaxCodeId
    {
        return $this->taxCodeId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function treatmentType(): TaxTreatmentType
    {
        return $this->treatmentType;
    }

    public function jurisdiction(): string
    {
        return $this->jurisdiction;
    }

    public function vatRate(): TaxRate
    {
        return $this->vatRate;
    }

    public function supplierVatMode(): SupplierVatMode
    {
        return $this->supplierVatMode;
    }

    public function deductibilityPolicy(): DeductibilityPolicy
    {
        return $this->deductibilityPolicy;
    }

    /** @return list<TaxLegDefinition> */
    public function legDefinitions(): array
    {
        return $this->legDefinitions;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function effectiveFrom(): ?DateTimeImmutable
    {
        return $this->effectiveFrom;
    }
}
