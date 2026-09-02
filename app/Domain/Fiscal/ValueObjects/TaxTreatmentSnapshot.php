<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Fiscal\Enums\TaxTreatmentType;

final readonly class TaxTreatmentSnapshot
{
    public function __construct(
        public TaxTreatmentDefinitionId $definitionId,
        public int $definitionVersion,
        public TaxTreatmentType $treatmentType,
        public string $jurisdiction,
        public SupplierVatMode $supplierVatMode,
        public TaxReportingClassification $reportingClassification,
        public DeductibilityBasisPoints $deductibility,
        public MoneyTaxBreakdown $amounts,
    ) {}
}
