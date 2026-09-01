<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Accounting;

use App\Application\Accounting\AccountingPeriodPlanReplacementStatus;
use App\Application\Accounting\CloseAccountingPeriod;
use App\Application\Accounting\CreateBookYear;
use App\Application\Accounting\ReopenAccountingPeriod;
use App\Application\Accounting\ReplaceAccountingPeriodPlan;
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

final class ReplaceAccountingPeriodPlanTest extends TestCase
{
    use RefreshDatabase;

    private const string A = 'ac110000-0000-4000-8000-000000000001';

    private const string B = 'ac110000-0000-4000-8000-000000000002';

    private const string ACTOR = 'ac110000-0000-4000-8000-000000000003';

    private const string YEAR = 'ac120000-0000-4000-8000-000000000001';

    private const string OLD_PERIOD = 'ac130000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        foreach ([[self::A, 'RPA'], [self::B, 'RPB']] as [$id, $code]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('domain_users')->insert(['id' => self::ACTOR, 'display_name' => 'Actor', 'email' => 'replace@example.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_open_unaudited_year_period_is_atomically_replaced_with_months(): void
    {
        $this->createYear();
        $before = $this->financialCounts();

        self::assertSame(AccountingPeriodPlanReplacementStatus::Success, $this->replace()->withMonthlyPeriods($this->admin(self::A), $this->yearId(), [self::OLD_PERIOD]));
        $periods = DB::table('accounting_periods')->where('book_year_id', self::YEAR)->orderBy('start_date')->get();
        self::assertCount(12, $periods);
        self::assertSame('2026-01-01', $periods->first()->start_date);
        self::assertSame('2026-01-31', $periods->first()->end_date);
        self::assertSame('2026-12-01', $periods->last()->start_date);
        self::assertSame('2026-12-31', $periods->last()->end_date);
        self::assertSame(['open'], $periods->pluck('status')->unique()->values()->all());
        self::assertSame($before, $this->financialCounts());
    }

    public function test_closed_period_is_denied_without_changes(): void
    {
        $this->createYear();
        $this->app->make(CloseAccountingPeriod::class)->execute($this->admin(self::A), $this->periodId(), 'close', $this->actor(), new DateTimeImmutable);

        self::assertSame(AccountingPeriodPlanReplacementStatus::PeriodClosed, $this->replace()->withMonthlyPeriods($this->admin(self::A), $this->yearId(), [self::OLD_PERIOD]));
        self::assertSame(1, DB::table('accounting_periods')->where('id', self::OLD_PERIOD)->count());
    }

    public function test_open_period_with_history_is_denied(): void
    {
        $this->createYear();
        $this->app->make(CloseAccountingPeriod::class)->execute($this->admin(self::A), $this->periodId(), 'close', $this->actor(), new DateTimeImmutable('2026-08-01'));
        $this->app->make(ReopenAccountingPeriod::class)->execute($this->admin(self::A), $this->periodId(), 'reopen', $this->actor(), new DateTimeImmutable('2026-08-02'));

        self::assertSame(AccountingPeriodPlanReplacementStatus::HistoryExists, $this->replace()->withMonthlyPeriods($this->admin(self::A), $this->yearId(), [self::OLD_PERIOD]));
    }

    public function test_gap_overlap_and_historical_uncovered_are_typed_and_non_mutating(): void
    {
        $this->createYear();
        $gap = [$this->period('ac130000-0000-4000-8000-000000000010', 'H1', '2026-01-01', '2026-05-31'), $this->period('ac130000-0000-4000-8000-000000000011', 'H2', '2026-07-01', '2026-12-31')];
        self::assertSame(AccountingPeriodPlanReplacementStatus::IncompleteCoverage, $this->replace()->replace($this->admin(self::A), $this->yearId(), [self::OLD_PERIOD], $gap));

        $overlap = [$this->period('ac130000-0000-4000-8000-000000000012', 'H1', '2026-01-01', '2026-07-01'), $this->period('ac130000-0000-4000-8000-000000000013', 'H2', '2026-07-01', '2026-12-31')];
        self::assertSame(AccountingPeriodPlanReplacementStatus::Overlap, $this->replace()->replace($this->admin(self::A), $this->yearId(), [self::OLD_PERIOD], $overlap));

        $this->historicalPosting('2026-06-15');
        self::assertSame(AccountingPeriodPlanReplacementStatus::HistoricalPostingDateUncovered, $this->replace()->replace($this->admin(self::A), $this->yearId(), [self::OLD_PERIOD], $gap));
        self::assertSame(1, DB::table('accounting_periods')->where('id', self::OLD_PERIOD)->count());
    }

    public function test_cross_tenant_replacement_is_not_found(): void
    {
        $this->createYear();
        self::assertSame(AccountingPeriodPlanReplacementStatus::NotFound, $this->replace()->withMonthlyPeriods($this->admin(self::B), $this->yearId(), [self::OLD_PERIOD]));
    }

    public function test_persistence_failure_rolls_back_entire_old_plan(): void
    {
        $this->createYear();
        $replacement = [
            $this->period('ac130000-0000-4000-8000-000000000020', 'DUP', '2026-01-01', '2026-06-30'),
            $this->period('ac130000-0000-4000-8000-000000000021', 'DUP', '2026-07-01', '2026-12-31'),
        ];

        self::assertSame(AccountingPeriodPlanReplacementStatus::IntegrityFailure, $this->replace()->replace($this->admin(self::A), $this->yearId(), [self::OLD_PERIOD], $replacement));
        self::assertSame(1, DB::table('accounting_periods')->where('id', self::OLD_PERIOD)->count());
        self::assertSame(1, DB::table('accounting_periods')->where('book_year_id', self::YEAR)->count());
    }

    private function createYear(): void
    {
        $year = new BookYear($this->yearId(), $this->admin(self::A), '2026', '2026', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'), [$this->period(self::OLD_PERIOD, '2026', '2026-01-01', '2026-12-31')]);
        self::assertSame('Success', $this->app->make(CreateBookYear::class)->execute($year)->name);
    }

    private function period(string $id, string $code, string $start, string $end): AccountingPeriod
    {
        return new AccountingPeriod(new AccountingPeriodId(new Uuid($id)), $this->admin(self::A), $this->yearId(), $code, $code, new DateTimeImmutable($start), new DateTimeImmutable($end));
    }

    private function historicalPosting(string $date): void
    {
        $now = now();
        DB::table('journals')->insert(['id' => 'ac140000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'code' => 'GEN', 'name' => 'General', 'type' => 'general', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journal_entries')->insert(['id' => 'ac150000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'journal_id' => 'ac140000-0000-4000-8000-000000000001', 'posting_date' => $date, 'reference' => 'historical', 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
    }

    private function financialCounts(): array
    {
        return array_map(static fn (string $table): int => DB::table($table)->count(), ['journal_entries', 'open_items', 'tax_postings', 'open_item_settlements', 'open_item_matches', 'bank_transaction_reversals']);
    }

    private function replace(): ReplaceAccountingPeriodPlan
    {
        return $this->app->make(ReplaceAccountingPeriodPlan::class);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function yearId(): BookYearId
    {
        return new BookYearId(new Uuid(self::YEAR));
    }

    private function periodId(): AccountingPeriodId
    {
        return new AccountingPeriodId(new Uuid(self::OLD_PERIOD));
    }

    private function actor(): UserId
    {
        return new UserId(new Uuid(self::ACTOR));
    }
}
