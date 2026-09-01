<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DateTimeImmutable;
use DomainException;

final class BookYear
{
    private array $periods = [];

    public function __construct(private readonly BookYearId $id, private readonly AdministrationId $admin, private readonly string $code, private string $label, private readonly DateTimeImmutable $start, private readonly DateTimeImmutable $end, array $periods = [])
    {
        if ($start > $end || trim($code) === '' || mb_strlen(trim($code)) > 50 || mb_strlen(trim($label)) > 100) {
            throw new DomainException('Invalid book year.');
        }$this->label = trim($label);
        foreach ($periods as $p) {
            $this->addPeriod($p);
        }
    }

    public function id(): BookYearId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->admin;
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

    public function periods(): array
    {
        return array_values($this->periods);
    }

    public function rename(string $v): void
    {
        if (mb_strlen(trim($v)) > 100) {
            throw new DomainException('Invalid label.');
        }$this->label = trim($v);
    }

    public function addPeriod(AccountingPeriod $p): void
    {
        if (! $p->administrationId()->equals($this->admin) || ! $p->bookYearId()->equals($this->id) || $p->startDate() < $this->start || $p->endDate() > $this->end) {
            throw new DomainException('Period outside book year.');
        }foreach ($this->periods as $x) {
            if ($p->startDate() <= $x->endDate() && $p->endDate() >= $x->startDate()) {
                throw new DomainException('Overlapping period.');
            }
        }$this->periods[$p->id()->toString()] = $p;
    }

    public function hasFullCoverage(): bool
    {
        $p = $this->periods();
        usort($p, fn ($a, $b) => $a->startDate() <=> $b->startDate());
        $next = $this->start;
        foreach ($p as $x) {
            if ($x->startDate() != $next) {
                return false;
            }$next = $x->endDate()->modify('+1 day');
        }

        return $next == $this->end->modify('+1 day');
    }
}
