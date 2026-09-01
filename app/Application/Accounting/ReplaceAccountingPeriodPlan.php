<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DateTimeImmutable;
use Throwable;

final readonly class ReplaceAccountingPeriodPlan
{
    public function __construct(
        private TransactionManager $transactions,
        private BookYearRepository $years,
        private AccountingPeriodHistoryReadRepository $history,
        private AccountingPeriodPlanIdentityGenerator $identities,
    ) {}

    /** @param list<string> $expectedPeriodIds */
    public function withMonthlyPeriods(AdministrationId $administrationId, BookYearId $bookYearId, array $expectedPeriodIds): AccountingPeriodPlanReplacementStatus
    {
        $year = $this->years->find($administrationId, $bookYearId);
        if ($year === null) {
            return AccountingPeriodPlanReplacementStatus::NotFound;
        }

        return $this->replace($administrationId, $bookYearId, $expectedPeriodIds, $this->monthlyPlan($year));
    }

    public function eligibility(AdministrationId $administrationId, BookYearId $bookYearId): AccountingPeriodPlanReplacementStatus
    {
        $year = $this->years->find($administrationId, $bookYearId);
        if ($year === null) {
            return AccountingPeriodPlanReplacementStatus::NotFound;
        }
        foreach ($year->periods() as $period) {
            if ($period->status() !== AccountingPeriodStatus::Open) {
                return AccountingPeriodPlanReplacementStatus::PeriodClosed;
            }
            if (($this->history->get($administrationId, $period->id())?->history ?? []) !== []) {
                return AccountingPeriodPlanReplacementStatus::HistoryExists;
            }
        }

        return AccountingPeriodPlanReplacementStatus::Success;
    }

    /**
     * @param  list<string>  $expectedPeriodIds
     * @param  list<AccountingPeriod>  $replacement
     */
    public function replace(AdministrationId $administrationId, BookYearId $bookYearId, array $expectedPeriodIds, array $replacement): AccountingPeriodPlanReplacementStatus
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $bookYearId, $expectedPeriodIds, $replacement): AccountingPeriodPlanReplacementStatus {
                $year = $this->years->find($administrationId, $bookYearId, true);
                if ($year === null) {
                    return AccountingPeriodPlanReplacementStatus::NotFound;
                }
                $currentIds = array_map(static fn (AccountingPeriod $period): string => $period->id()->toString(), $year->periods());
                sort($currentIds);
                sort($expectedPeriodIds);
                if ($currentIds !== $expectedPeriodIds) {
                    return AccountingPeriodPlanReplacementStatus::IntegrityFailure;
                }
                foreach ($year->periods() as $period) {
                    if ($period->status() !== AccountingPeriodStatus::Open) {
                        return AccountingPeriodPlanReplacementStatus::PeriodClosed;
                    }
                    if (($this->history->get($administrationId, $period->id())?->history ?? []) !== []) {
                        return AccountingPeriodPlanReplacementStatus::HistoryExists;
                    }
                }

                $validation = $this->validate($administrationId, $bookYearId, $year->startDate(), $year->endDate(), $replacement);
                if ($validation !== AccountingPeriodPlanReplacementStatus::Success) {
                    return $validation;
                }

                return $this->years->replacePeriodPlan($administrationId, $bookYearId, $expectedPeriodIds, $replacement)
                    ? AccountingPeriodPlanReplacementStatus::Success
                    : AccountingPeriodPlanReplacementStatus::IntegrityFailure;
            });
        } catch (Throwable) {
            return AccountingPeriodPlanReplacementStatus::IntegrityFailure;
        }
    }

    /** @param list<AccountingPeriod> $periods */
    private function validate(AdministrationId $administrationId, BookYearId $bookYearId, DateTimeImmutable $start, DateTimeImmutable $end, array $periods): AccountingPeriodPlanReplacementStatus
    {
        usort($periods, static fn (AccountingPeriod $left, AccountingPeriod $right): int => $left->startDate() <=> $right->startDate());
        $previousEnd = null;
        foreach ($periods as $period) {
            if (! $period->administrationId()->equals($administrationId) || ! $period->bookYearId()->equals($bookYearId) || $period->startDate() < $start || $period->endDate() > $end) {
                return AccountingPeriodPlanReplacementStatus::IntegrityFailure;
            }
            if ($previousEnd !== null && $period->startDate() <= $previousEnd) {
                return AccountingPeriodPlanReplacementStatus::Overlap;
            }
            $previousEnd = $period->endDate();
        }

        foreach ($this->years->historicalPostingDates($administrationId) as $date) {
            $postingDate = new DateTimeImmutable($date);
            if ($postingDate < $start || $postingDate > $end) {
                continue;
            }
            $matches = array_filter($periods, static fn (AccountingPeriod $period): bool => $postingDate >= $period->startDate() && $postingDate <= $period->endDate());
            if (count($matches) !== 1) {
                return AccountingPeriodPlanReplacementStatus::HistoricalPostingDateUncovered;
            }
        }

        $next = $start;
        foreach ($periods as $period) {
            if ($period->startDate() != $next) {
                return AccountingPeriodPlanReplacementStatus::IncompleteCoverage;
            }
            $next = $period->endDate()->modify('+1 day');
        }

        return $next == $end->modify('+1 day')
            ? AccountingPeriodPlanReplacementStatus::Success
            : AccountingPeriodPlanReplacementStatus::IncompleteCoverage;
    }

    /** @return list<AccountingPeriod> */
    private function monthlyPlan(BookYear $year): array
    {
        $periods = [];
        $cursor = $year->startDate();
        while ($cursor <= $year->endDate()) {
            $periodEnd = min($cursor->modify('last day of this month'), $year->endDate());
            $code = $cursor->format('Y-m');
            $periods[] = new AccountingPeriod($this->identities->periodId(), $year->administrationId(), $year->id(), $code, $code, $cursor, $periodEnd);
            $cursor = $periodEnd->modify('+1 day');
        }

        return $periods;
    }
}
