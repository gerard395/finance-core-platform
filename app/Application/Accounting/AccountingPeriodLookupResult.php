<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;

final readonly class AccountingPeriodLookupResult
{
    private function __construct(
        public AccountingPeriodLookupStatus $status,
        public ?AccountingPeriodId $periodId = null,
        public ?AccountingPeriodStatus $periodStatus = null,
    ) {}

    public static function found(AccountingPeriodId $periodId, AccountingPeriodStatus $status): self
    {
        return new self(AccountingPeriodLookupStatus::Found, $periodId, $status);
    }

    public static function noPeriod(): self
    {
        return new self(AccountingPeriodLookupStatus::NoPeriod);
    }

    public static function integrityFailure(): self
    {
        return new self(AccountingPeriodLookupStatus::IntegrityFailure);
    }
}
