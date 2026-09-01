<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;

final readonly class AccountingPeriodPostingDecision
{
    public function __construct(
        public AccountingPeriodPostingDecisionStatus $status,
        public ?BookYearId $bookYearId = null,
        public ?AccountingPeriodId $accountingPeriodId = null,
    ) {}
}
