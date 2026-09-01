<?php

declare(strict_types=1);

namespace App\Application\Accounting;

enum AccountingPeriodReadinessStatus: string
{
    case Success = 'success';
    case NoBookYear = 'no_book_year';
    case IncompleteCoverage = 'incomplete_coverage';
    case OverlapIntegrityFailure = 'overlap_integrity_failure';
}
