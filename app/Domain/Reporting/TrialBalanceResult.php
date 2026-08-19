<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class TrialBalanceResult
{
    /**
     * @param  list<TrialBalanceLine>  $lines
     */
    public function __construct(
        private array $lines,
        private Money $totalDebit,
        private Money $totalCredit,
        private AdministrationId $administrationId,
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
        private Currency $currency,
    ) {
        if ($this->startDate > $this->endDate) {
            throw new DomainException('Trial balance result start date cannot be after end date.');
        }

        if (! $this->currency->equals($this->totalDebit->currency())
            || ! $this->currency->equals($this->totalCredit->currency())) {
            throw new DomainException('A trial balance result must use one currency.');
        }

        foreach ($this->lines as $line) {
            if (! $this->currency->equals($line->totalDebit()->currency())
                || ! $this->currency->equals($line->totalCredit()->currency())
                || ! $this->currency->equals($line->balance()->currency())) {
                throw new DomainException('A trial balance result must use one currency.');
            }
        }
    }

    /** @return list<TrialBalanceLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalDebit(): Money
    {
        return $this->totalDebit;
    }

    public function totalCredit(): Money
    {
        return $this->totalCredit;
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit->equals($this->totalCredit);
    }

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
}
