<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DateTimeImmutable;
use DomainException;

final class AccountingPeriod
{
    public function __construct(private readonly AccountingPeriodId $id, private readonly AdministrationId $admin, private readonly BookYearId $year, private readonly string $code, private string $label, private readonly DateTimeImmutable $start, private readonly DateTimeImmutable $end, private AccountingPeriodStatus $status = AccountingPeriodStatus::Open)
    {
        if ($start > $end || trim($code) === '' || trim($label) === '') {
            throw new DomainException('Invalid accounting period.');
        }
    }

    public function id(): AccountingPeriodId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->admin;
    }

    public function bookYearId(): BookYearId
    {
        return $this->year;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->start;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->end;
    }

    public function status(): AccountingPeriodStatus
    {
        return $this->status;
    }

    public function rename(string $v): void
    {
        if (trim($v) === '') {
            throw new DomainException('Label required.');
        }$this->label = trim($v);
    }

    public function close(): void
    {
        if ($this->status !== AccountingPeriodStatus::Open) {
            throw new DomainException('Invalid state.');
        }$this->status = AccountingPeriodStatus::Closed;
    }

    public function reopen(): void
    {
        if ($this->status !== AccountingPeriodStatus::Closed) {
            throw new DomainException('Invalid state.');
        }$this->status = AccountingPeriodStatus::Open;
    }
}
