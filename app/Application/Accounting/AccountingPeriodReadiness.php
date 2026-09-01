<?php

declare(strict_types=1);

namespace App\Application\Accounting;

final readonly class AccountingPeriodReadiness
{
    public function __construct(public AccountingPeriodReadinessStatus $status, public array $uncoveredPostingDates = []) {}
}
