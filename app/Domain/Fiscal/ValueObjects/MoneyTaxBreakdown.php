<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

use App\Domain\Shared\Finance\Money;
use InvalidArgumentException;

final readonly class MoneyTaxBreakdown
{
    public function __construct(
        public Money $supplierNet,
        public Money $supplierTaxCharged,
        public Money $supplierGross,
        public Money $selfAssessedVat,
        public Money $deductibleVat,
        public Money $nonDeductibleTaxCost,
    ) {
        foreach ([$supplierTaxCharged, $supplierGross, $selfAssessedVat, $deductibleVat, $nonDeductibleTaxCost] as $amount) {
            if (! $supplierNet->currency()->equals($amount->currency()) || $amount->isNegative()) {
                throw new InvalidArgumentException('Tax breakdown amounts must be non-negative and use one currency.');
            }
        }

        if ($supplierNet->isNegative() || ! $supplierGross->equals($supplierNet->add($supplierTaxCharged))) {
            throw new InvalidArgumentException('Supplier gross must equal supplier net plus supplier tax charged.');
        }

        if (! $this->assessedVat()->equals($deductibleVat->add($nonDeductibleTaxCost))) {
            throw new InvalidArgumentException('Deductible plus non-deductible VAT must equal assessed VAT.');
        }
    }

    public function assessedVat(): Money
    {
        return $this->supplierTaxCharged->add($this->selfAssessedVat);
    }
}
