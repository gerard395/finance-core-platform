<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Accounting;

use App\Application\Accounting\AccountingPeriodHistoryReadRepository;
use App\Application\Accounting\AccountingPeriodLockMode;
use App\Application\Accounting\AccountingPeriodLookupRepository;
use App\Application\Accounting\AccountingPeriodLookupStatus;
use App\Application\Accounting\AccountingPeriodMutationStatus;
use App\Application\Accounting\AccountingPeriodReadinessStatus;
use App\Application\Accounting\BookYearRepository;
use App\Application\Accounting\CloseAccountingPeriod;
use App\Application\Accounting\CreateAccountingPeriod;
use App\Application\Accounting\CreateBookYear;
use App\Application\Accounting\GetAccountingPeriodReadiness;
use App\Application\Accounting\ReopenAccountingPeriod;
use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AccountingPeriodReadinessAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'aa210000-0000-4000-8000-000000000001';

    private const B = 'aa210000-0000-4000-8000-000000000002';

    private const ACTOR = 'aa220000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        foreach ([[self::A, 'APA'], [self::B, 'APB']] as [$id, $code]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('domain_users')->insert(['id' => self::ACTOR, 'display_name' => 'Actor', 'email' => 'ap-isolation@example.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_readiness_distinguishes_no_year_incomplete_and_success(): void
    {
        $readiness = $this->app->make(GetAccountingPeriodReadiness::class);
        self::assertSame(AccountingPeriodReadinessStatus::NoBookYear, $readiness->forAdministration($this->admin(self::A))->status);

        $empty = $this->year(self::A, 'aa230000-0000-4000-8000-000000000001', 'FY26', '2026-01-01', '2026-12-31');
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(CreateBookYear::class)->execute($empty));
        self::assertSame(AccountingPeriodReadinessStatus::IncompleteCoverage, $readiness->forAdministration($this->admin(self::A))->status);

        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(CreateAccountingPeriod::class)->execute($this->period(self::A, $empty, 'aa240000-0000-4000-8000-000000000001', '2026-01-01', '2026-12-31')));
        self::assertSame(AccountingPeriodReadinessStatus::Success, $readiness->forAdministration($this->admin(self::A))->status);
    }

    public function test_multiple_complete_years_may_have_gaps_but_historical_dates_must_be_covered(): void
    {
        $first = $this->completeYear(self::A, 'aa230000-0000-4000-8000-000000000011', 'FY25', '2025-01-01', '2025-12-31');
        $this->completeYear(self::A, 'aa230000-0000-4000-8000-000000000012', 'FY27', '2027-01-01', '2027-12-31');
        self::assertSame(AccountingPeriodReadinessStatus::Success, $this->app->make(GetAccountingPeriodReadiness::class)->forAdministration($this->admin(self::A))->status);

        $now = now();
        DB::table('journals')->insert(['id' => 'aa250000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'code' => 'GEN', 'name' => 'General', 'type' => 'general', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journal_entries')->insert(['id' => 'aa260000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'journal_id' => 'aa250000-0000-4000-8000-000000000001', 'posting_date' => '2026-06-01', 'reference' => 'gap', 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
        $result = $this->app->make(GetAccountingPeriodReadiness::class)->forAdministration($this->admin(self::A));
        self::assertSame(AccountingPeriodReadinessStatus::IncompleteCoverage, $result->status);
        self::assertSame(['2026-06-01'], $result->uncoveredPostingDates);
        self::assertTrue($first->hasFullCoverage());
    }

    public function test_history_readmodel_is_ordered_tenant_scoped_and_typed(): void
    {
        $year = $this->completeYear(self::A, 'aa230000-0000-4000-8000-000000000021', 'FY26', '2026-01-01', '2026-12-31');
        $period = $year->periods()[0];
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(CloseAccountingPeriod::class)->execute($this->admin(self::A), $period->id(), 'year close', $this->actor(), new DateTimeImmutable('2026-12-31 12:00:00')));
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(ReopenAccountingPeriod::class)->execute($this->admin(self::A), $period->id(), 'audited reopen', $this->actor(), new DateTimeImmutable('2027-01-01 09:00:00')));

        $reader = $this->app->make(AccountingPeriodHistoryReadRepository::class);
        $model = $reader->get($this->admin(self::A), $period->id());
        self::assertNotNull($model);
        self::assertSame($period->id()->toString(), $model->accountingPeriodId->toString());
        self::assertSame('open', $model->currentStatus->value);
        self::assertCount(2, $model->history);
        self::assertSame('open', $model->history[0]->fromStatus->value);
        self::assertSame('closed', $model->history[0]->toStatus->value);
        self::assertSame('year close', $model->history[0]->reason);
        self::assertSame(self::ACTOR, $model->history[0]->actor->toString());
        self::assertSame('2026-12-31 12:00:00', $model->history[0]->occurredAt->format('Y-m-d H:i:s'));
        self::assertSame('closed', $model->history[1]->fromStatus->value);
        self::assertSame('open', $model->history[1]->toStatus->value);
        self::assertSame('audited reopen', $model->history[1]->reason);
        self::assertSame('2027-01-01 09:00:00', $model->history[1]->occurredAt->format('Y-m-d H:i:s'));
        self::assertNull($reader->get($this->admin(self::B), $period->id()));
    }

    public function test_history_readmodel_uses_causality_for_adversarial_same_timestamp_ids(): void
    {
        $at = '2026-09-01 10:00:00';
        $cases = [
            [self::A, 'aa230000-0000-4000-8000-000000000022', 'FY26', 'ffffffff-ffff-4fff-8fff-ffffffffffff', '00000000-0000-4000-8000-000000000001'],
            [self::B, 'aa230000-0000-4000-8000-000000000023', 'FY27', '00000000-0000-4000-8000-000000000002', 'ffffffff-ffff-4fff-8fff-fffffffffffe'],
        ];

        foreach ($cases as [$administration, $yearId, $code, $closeId, $reopenId]) {
            $year = $this->completeYear($administration, $yearId, $code, '2026-01-01', '2026-12-31');
            $period = $year->periods()[0];
            DB::table('accounting_period_status_history')->insert([
                [
                    'id' => $closeId, 'administration_id' => $administration,
                    'book_year_id' => $year->id()->toString(), 'accounting_period_id' => $period->id()->toString(),
                    'from_status' => 'open', 'to_status' => 'closed', 'reason' => 'close',
                    'actor_id' => self::ACTOR, 'occurred_at' => $at,
                ],
                [
                    'id' => $reopenId, 'administration_id' => $administration,
                    'book_year_id' => $year->id()->toString(), 'accounting_period_id' => $period->id()->toString(),
                    'from_status' => 'closed', 'to_status' => 'open', 'reason' => 'reopen',
                    'actor_id' => self::ACTOR, 'occurred_at' => $at,
                ],
            ]);

            $model = $this->app->make(AccountingPeriodHistoryReadRepository::class)->get($this->admin($administration), $period->id());

            self::assertNotNull($model);
            self::assertSame(['close', 'reopen'], array_map(static fn ($entry) => $entry->reason, $model->history));
        }
    }

    public function test_tenant_reads_writes_overlap_and_codes_are_isolated(): void
    {
        $a = $this->completeYear(self::A, 'aa230000-0000-4000-8000-000000000031', 'FY26', '2026-01-01', '2026-12-31');
        $b = $this->completeYear(self::B, 'aa230000-0000-4000-8000-000000000032', 'FY26', '2026-01-01', '2026-12-31');
        $repo = $this->app->make(BookYearRepository::class);
        self::assertNull($repo->find($this->admin(self::B), $a->id()));
        self::assertNull($repo->find($this->admin(self::A), $b->id()));
        self::assertTrue($repo->overlaps($this->admin(self::A), new DateTimeImmutable('2026-06-01'), new DateTimeImmutable('2026-06-30')));
        self::assertTrue($repo->overlaps($this->admin(self::B), new DateTimeImmutable('2026-06-01'), new DateTimeImmutable('2026-06-30')));
        self::assertSame(2, DB::table('book_years')->where('code', 'FY26')->count());

        $crossTenant = new AccountingPeriod(new AccountingPeriodId(new Uuid('aa240000-0000-4000-8000-000000000099')), $this->admin(self::B), $a->id(), 'X', 'Cross', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'));
        self::assertSame(AccountingPeriodMutationStatus::NotFound, $this->app->make(CreateAccountingPeriod::class)->execute($crossTenant));
        self::assertSame(0, DB::table('accounting_periods')->where('id', 'aa240000-0000-4000-8000-000000000099')->count());
    }

    public function test_posting_date_lookup_is_typed_tenant_safe_and_lockable(): void
    {
        $year = $this->completeYear(self::A, 'aa230000-0000-4000-8000-000000000041', 'FY26', '2026-01-01', '2026-12-31');
        $reader = $this->app->make(AccountingPeriodLookupRepository::class);
        foreach ([AccountingPeriodLockMode::None, AccountingPeriodLockMode::Shared, AccountingPeriodLockMode::Exclusive] as $lockMode) {
            $found = $reader->forPostingDate($this->admin(self::A), new DateTimeImmutable('2026-08-26'), $lockMode);
            self::assertSame(AccountingPeriodLookupStatus::Found, $found->status);
            self::assertSame($year->periods()[0]->id()->toString(), $found->periodId?->toString());
            self::assertSame('open', $found->periodStatus?->value);
        }
        self::assertSame(AccountingPeriodLookupStatus::NoPeriod, $reader->forPostingDate($this->admin(self::B), new DateTimeImmutable('2026-08-26'))->status);

        DB::table('accounting_periods')->insert([
            'id' => 'aa240000-0000-4000-8000-000000000042', 'administration_id' => self::A,
            'book_year_id' => $year->id()->toString(), 'code' => 'OVERLAP', 'label' => 'Overlap',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        self::assertSame(AccountingPeriodLookupStatus::IntegrityFailure, $reader->forPostingDate($this->admin(self::A), new DateTimeImmutable('2026-08-26'))->status);
    }

    private function completeYear(string $admin, string $id, string $code, string $start, string $end): BookYear
    {
        $year = $this->year($admin, $id, $code, $start, $end);
        $year->addPeriod($this->period($admin, $year, str_replace('aa23', 'aa24', $id), $start, $end));
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(CreateBookYear::class)->execute($year));

        return $year;
    }

    private function year(string $admin, string $id, string $code, string $start, string $end): BookYear
    {
        return new BookYear(new BookYearId(new Uuid($id)), $this->admin($admin), $code, $code, new DateTimeImmutable($start), new DateTimeImmutable($end));
    }

    private function period(string $admin, BookYear $year, string $id, string $start, string $end): AccountingPeriod
    {
        return new AccountingPeriod(new AccountingPeriodId(new Uuid($id)), $this->admin($admin), $year->id(), 'P1', 'Period', new DateTimeImmutable($start), new DateTimeImmutable($end));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function actor(): UserId
    {
        return new UserId(new Uuid(self::ACTOR));
    }
}
