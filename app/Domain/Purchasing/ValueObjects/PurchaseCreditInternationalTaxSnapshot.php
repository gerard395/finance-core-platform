<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentGroupId;
use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class PurchaseCreditInternationalTaxSnapshot
{
    /** @param non-empty-list<TaxPostingId> $originalTaxPostingIds */
    public function __construct(
        public PurchaseTaxTreatmentSnapshot $treatment,
        public TaxTreatmentGroupId $sourceGroupId,
        public array $originalTaxPostingIds,
        public Money $supplierGross,
        public Money $selfAssessedVat,
        public Money $deductibleVat,
        public Money $nonDeductibleTaxCost,
    ) {
        if ($originalTaxPostingIds === []) {
            throw new InvalidArgumentException('An international credit snapshot requires the complete original treatment group.');
        }
        $seen = [];
        foreach ($originalTaxPostingIds as $id) {
            if (isset($seen[$id->toString()])) {
                throw new InvalidArgumentException('Original tax posting identities must be unique.');
            }
            $seen[$id->toString()] = true;
        }
        foreach ([$selfAssessedVat, $deductibleVat, $nonDeductibleTaxCost] as $amount) {
            if (! $supplierGross->currency()->equals($amount->currency()) || $amount->isNegative()) {
                throw new InvalidArgumentException('International credit amounts must be non-negative and use one currency.');
            }
        }
        if (! $selfAssessedVat->equals($deductibleVat->add($nonDeductibleTaxCost))) {
            throw new InvalidArgumentException('International credit VAT split must equal self-assessed VAT.');
        }
    }
}
