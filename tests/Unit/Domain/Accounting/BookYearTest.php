<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting;

use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\Entities\PeriodStatusHistory;
use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Accounting\ValueObjects\PeriodStatusHistoryId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class BookYearTest extends TestCase
{
    public function test_custom_periods_cover_year_and_status_transitions_are_strict(): void
    {
        $a = new AdministrationId(new Uuid('a9110000-0000-4000-8000-000000000001'));
        $yid = new BookYearId(new Uuid('a9120000-0000-4000-8000-000000000001'));
        $y = new BookYear($yid, $a, 'FY26', 'FY 2026', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'));
        $p1 = new AccountingPeriod(new AccountingPeriodId(new Uuid('a9130000-0000-4000-8000-000000000001')), $a, $yid, 'H1', 'H1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-06-30'));
        $p2 = new AccountingPeriod(new AccountingPeriodId(new Uuid('a9130000-0000-4000-8000-000000000002')), $a, $yid, 'H2', 'H2', new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-12-31'));
        $y->addPeriod($p1);
        self::assertFalse($y->hasFullCoverage());
        $y->addPeriod($p2);
        self::assertTrue($y->hasFullCoverage());
        self::assertSame(AccountingPeriodStatus::Open, $p1->status());
        $p1->close();
        self::assertSame(AccountingPeriodStatus::Closed, $p1->status());
        $p1->reopen();
        self::assertSame(AccountingPeriodStatus::Open, $p1->status());
    }

    public function test_invalid_ranges_overlap_and_blank_reason_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        new BookYear(new BookYearId(new Uuid('a9120000-0000-4000-8000-000000000003')), new AdministrationId(new Uuid('a9110000-0000-4000-8000-000000000001')), 'X', 'X', new DateTimeImmutable('2027-02-01'), new DateTimeImmutable('2027-01-01'));
    }

    public function test_history_reason_is_trimmed_and_required(): void
    {
        $h = new PeriodStatusHistory(new PeriodStatusHistoryId(new Uuid('a9140000-0000-4000-8000-000000000001')), new AdministrationId(new Uuid('a9110000-0000-4000-8000-000000000001')), new BookYearId(new Uuid('a9120000-0000-4000-8000-000000000001')), new AccountingPeriodId(new Uuid('a9130000-0000-4000-8000-000000000001')), AccountingPeriodStatus::Open, AccountingPeriodStatus::Closed, ' close ', new UserId(new Uuid('a9150000-0000-4000-8000-000000000001')), new DateTimeImmutable);
        self::assertSame('close', $h->reason);
    }
}
