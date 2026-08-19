<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class ProfitAndLossResult
{
    /**
     * @param  list<ProfitAndLossLine>  $revenue
     * @param  list<ProfitAndLossLine>  $expenses
     */
    public function __construct(
        private AdministrationId $administrationId,
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
        private Currency $currency,
        private array $revenue,
        private array $expenses,
        private Money $totalRevenue,
        private Money $totalExpenses,
        private Money $netResult,
    ) {
        $amounts = [$this->totalRevenue, $this->totalExpenses, $this->netResult];

        foreach ([$this->revenue, $this->expenses] as $lines) {
            foreach ($lines as $line) {
                $amounts[] = $line->amount();
            }
        }

        foreach ($amounts as $amount) {
            if (! $this->currency->equals($amount->currency())) {
                throw new DomainException('A profit and loss result must use one currency.');
            }
        }
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

    /** @return list<ProfitAndLossLine> */
    public function revenue(): array
    {
        return $this->revenue;
    }

    /** @return list<ProfitAndLossLine> */
    public function expenses(): array
    {
        return $this->expenses;
    }

    public function totalRevenue(): Money
    {
        return $this->totalRevenue;
    }

    public function totalExpenses(): Money
    {
        return $this->totalExpenses;
    }

    public function netResult(): Money
    {
        return $this->netResult;
    }
}
