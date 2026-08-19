<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class GeneralLedgerResult
{
    /** @param list<GeneralLedgerLine> $lines */
    public function __construct(
        private AdministrationId $administrationId,
        private DateTimeImmutable $startDate,
        private DateTimeImmutable $endDate,
        private Currency $currency,
        private array $lines,
        private Money $totalDebit,
        private Money $totalCredit,
        private Money $closingMovementBalance,
    ) {
        if ($this->startDate > $this->endDate) {
            throw new DomainException('General ledger start date cannot be after end date.');
        }

        $amounts = [$this->totalDebit, $this->totalCredit, $this->closingMovementBalance];

        foreach ($this->lines as $line) {
            $amounts[] = $line->debit();
            $amounts[] = $line->credit();
            $amounts[] = $line->runningBalance();
        }

        foreach ($amounts as $amount) {
            if (! $this->currency->equals($amount->currency())) {
                throw new DomainException('A general ledger result must use one currency.');
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

    /** @return list<GeneralLedgerLine> */
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

    public function closingMovementBalance(): Money
    {
        return $this->closingMovementBalance;
    }
}
