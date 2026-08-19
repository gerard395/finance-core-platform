<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class VatOverviewResult
{
    public function __construct(private AdministrationId $administrationId, private DateTimeImmutable $startDate, private DateTimeImmutable $endDate, private Currency $currency, private array $lines, private array $taxCodeSummaries, private Money $totalOutputTaxableBase, private Money $totalOutputTax, private Money $totalInputTaxableBase, private Money $totalInputTax) {}

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function lines(): array
    {
        return $this->lines;
    }

    public function taxCodeSummaries(): array
    {
        return $this->taxCodeSummaries;
    }

    public function totalOutputTaxableBase(): Money
    {
        return $this->totalOutputTaxableBase;
    }

    public function totalOutputTax(): Money
    {
        return $this->totalOutputTax;
    }

    public function totalInputTaxableBase(): Money
    {
        return $this->totalInputTaxableBase;
    }

    public function totalInputTax(): Money
    {
        return $this->totalInputTax;
    }

    public function netVatPosition(): Money
    {
        return $this->totalOutputTax->subtract($this->totalInputTax);
    }
}
