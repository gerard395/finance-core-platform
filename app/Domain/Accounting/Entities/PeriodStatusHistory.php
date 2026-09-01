<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Accounting\ValueObjects\PeriodStatusHistoryId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;
use DomainException;

final readonly class PeriodStatusHistory
{
    public string $reason;

    public function __construct(public PeriodStatusHistoryId $id, public AdministrationId $administrationId, public BookYearId $bookYearId, public AccountingPeriodId $accountingPeriodId, public AccountingPeriodStatus $from, public AccountingPeriodStatus $to, string $reason, public UserId $actor, public DateTimeImmutable $occurredAt)
    {
        $this->reason = trim($reason);
        if ($this->reason === '' || mb_strlen($this->reason) > 500 || $from === $to) {
            throw new DomainException('Invalid status history.');
        }
    }
}
