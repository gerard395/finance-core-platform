<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\ValueObjects;

final readonly class PurchaseTaxCalculationResult
{
    /** @param list<TaxLeg> $legs */
    public function __construct(
        public MoneyTaxBreakdown $amounts,
        public TaxTreatmentSnapshot $snapshot,
        public array $legs,
    ) {}
}
