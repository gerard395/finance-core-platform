<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class TaxPostingLegSnapshot
{
    public function __construct(
        public TaxTreatmentDefinitionId $definitionId,
        public int $definitionVersion,
        public TaxTreatmentGroupId $groupId,
        public TaxLegRole $role,
        public TaxTreatmentType $treatmentType,
        public string $jurisdiction,
        public TaxReportingClassification $reportingClassification,
        public DeductibilityBasisPoints $deductibility,
        public Money $assessedVat,
        public Money $deductibleVat,
        public Money $nonDeductibleTaxCost,
        public SupplierVatMode $supplierVatMode,
    ) {
        if ($definitionVersion < 1 || preg_match('/\A[A-Z]{2}\z/', $jurisdiction) !== 1) {
            throw new InvalidArgumentException('Tax posting treatment metadata is invalid.');
        }
        foreach ([$deductibleVat, $nonDeductibleTaxCost] as $amount) {
            if (! $assessedVat->currency()->equals($amount->currency()) || $amount->isNegative()) {
                throw new InvalidArgumentException('Tax posting treatment amounts must be non-negative and use one currency.');
            }
        }
        if ($assessedVat->isNegative() || ! $assessedVat->equals($deductibleVat->add($nonDeductibleTaxCost))) {
            throw new InvalidArgumentException('Tax posting treatment split must equal assessed VAT.');
        }
    }
}
