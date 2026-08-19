<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Finance\Money;

final readonly class VatOverviewTaxCodeSummary
{
    public function __construct(private TaxCodeId $taxCodeId, private TaxRate $taxRate, private Money $outputTaxableBase, private Money $outputTax, private Money $inputTaxableBase, private Money $inputTax) {}

    public function taxCodeId(): TaxCodeId
    {
        return $this->taxCodeId;
    }

    public function taxRate(): TaxRate
    {
        return $this->taxRate;
    }

    public function outputTaxableBase(): Money
    {
        return $this->outputTaxableBase;
    }

    public function outputTax(): Money
    {
        return $this->outputTax;
    }

    public function inputTaxableBase(): Money
    {
        return $this->inputTaxableBase;
    }

    public function inputTax(): Money
    {
        return $this->inputTax;
    }

    public function netTaxEffect(): Money
    {
        return $this->outputTax->subtract($this->inputTax);
    }
}
