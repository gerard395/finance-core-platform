<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

interface BookYearRepository
{
    public function save(BookYear $year): bool;

    public function updateLabel(AdministrationId $a, BookYearId $id, string $label): bool;

    public function find(AdministrationId $a, BookYearId $id, bool $lock = false): ?BookYear;

    /** @return list<BookYear> */
    public function allForAdministration(AdministrationId $a): array;

    public function savePeriod(AccountingPeriod $period): bool;

    /**
     * @param  list<string>  $expectedPeriodIds
     * @param  list<AccountingPeriod>  $replacement
     */
    public function replacePeriodPlan(AdministrationId $a, BookYearId $id, array $expectedPeriodIds, array $replacement): bool;

    public function overlaps(AdministrationId $a, DateTimeImmutable $start, DateTimeImmutable $end, ?BookYearId $except = null): bool;

    public function lockAdministration(AdministrationId $a): void;

    public function historicalPostingDates(AdministrationId $a): array;

    public function uncoveredPostingDates(AdministrationId $a): array;

    public function transition(AdministrationId $a, AccountingPeriodId $id, string $reason, UserId $actor, DateTimeImmutable $at, bool $reopen = false): PeriodTransitionResult;
}
