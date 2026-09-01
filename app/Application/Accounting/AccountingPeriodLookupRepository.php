<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use DateTimeImmutable;

interface AccountingPeriodLookupRepository
{
    public function forPostingDate(
        AdministrationId $administrationId,
        DateTimeImmutable $postingDate,
        AccountingPeriodLockMode $lockMode = AccountingPeriodLockMode::None,
    ): AccountingPeriodLookupResult;
}
