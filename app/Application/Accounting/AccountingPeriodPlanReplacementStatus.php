<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum AccountingPeriodPlanReplacementStatus: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case PeriodClosed = 'period_closed';
    case HistoryExists = 'history_exists';
    case IncompleteCoverage = 'incomplete_coverage';
    case Overlap = 'overlap';
    case HistoricalPostingDateUncovered = 'historical_posting_date_uncovered';
    case IntegrityFailure = 'integrity_failure';
}
