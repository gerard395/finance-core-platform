<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;

final readonly class AccountingPeriodHistoryReadModel
{
    /** @param list<AccountingPeriodHistoryEntry> $history */
    public function __construct(
        public AccountingPeriodId $accountingPeriodId,
        public AccountingPeriodStatus $currentStatus,
        public array $history,
    ) {}
}
