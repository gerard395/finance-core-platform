<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class AccountingPeriodPostingGuard
{
    public function __construct(private AccountingPeriodLookupRepository $periods) {}

    public function lockForPosting(AdministrationId $administrationId, PostingDate $postingDate): AccountingPeriodPostingDecision
    {
        $result = $this->periods->forPostingDate($administrationId, $postingDate->value(), AccountingPeriodLockMode::Shared);

        return match ($result->status) {
            AccountingPeriodLookupStatus::NoPeriod => new AccountingPeriodPostingDecision(AccountingPeriodPostingDecisionStatus::NoPeriod),
            AccountingPeriodLookupStatus::IntegrityFailure => new AccountingPeriodPostingDecision(AccountingPeriodPostingDecisionStatus::IntegrityFailure),
            AccountingPeriodLookupStatus::Found => new AccountingPeriodPostingDecision(
                $result->periodStatus === AccountingPeriodStatus::Open
                    ? AccountingPeriodPostingDecisionStatus::Open
                    : AccountingPeriodPostingDecisionStatus::Closed,
                $result->bookYearId,
                $result->periodId,
            ),
        };
    }
}
