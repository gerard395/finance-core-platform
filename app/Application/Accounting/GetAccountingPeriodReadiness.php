<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;

final readonly class GetAccountingPeriodReadiness
{
    public function __construct(private BookYearRepository $years) {}

    public function forAdministration(AdministrationId $a): AccountingPeriodReadiness
    {
        try {
            $years = $this->years->allForAdministration($a);
        } catch (DomainException) {
            return new AccountingPeriodReadiness(AccountingPeriodReadinessStatus::OverlapIntegrityFailure);
        }
        if ($years === []) {
            return new AccountingPeriodReadiness(AccountingPeriodReadinessStatus::NoBookYear);
        }

        $previousEnd = null;
        foreach ($years as $year) {
            if ($previousEnd !== null && $year->startDate() <= $previousEnd) {
                return new AccountingPeriodReadiness(AccountingPeriodReadinessStatus::OverlapIntegrityFailure);
            }
            if (! $year->hasFullCoverage()) {
                return new AccountingPeriodReadiness(AccountingPeriodReadinessStatus::IncompleteCoverage, $this->years->uncoveredPostingDates($a));
            }
            $previousEnd = $year->endDate();
        }

        $u = $this->years->uncoveredPostingDates($a);

        return new AccountingPeriodReadiness($u === [] ? AccountingPeriodReadinessStatus::Success : AccountingPeriodReadinessStatus::IncompleteCoverage, $u);
    }
}
