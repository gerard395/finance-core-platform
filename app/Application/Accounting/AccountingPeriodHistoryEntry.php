<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class AccountingPeriodHistoryEntry
{
    public function __construct(
        public AccountingPeriodStatus $fromStatus,
        public AccountingPeriodStatus $toStatus,
        public string $reason,
        public UserId $actor,
        public DateTimeImmutable $occurredAt,
    ) {}
}
