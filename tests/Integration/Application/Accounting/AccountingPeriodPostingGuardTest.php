<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Accounting;

use App\Application\Accounting\AccountingPeriodPostingDecisionStatus;
use App\Application\Accounting\AccountingPeriodPostingGuard;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AccountingPeriodPostingGuardTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'ac210000-0000-4000-8000-000000000001';

    private const B = 'ac210000-0000-4000-8000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([[self::A, 'GA'], [self::B, 'GB']] as [$id, $code]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->createOpenAccountingPeriodFixture(self::A);
    }

    public function test_guard_returns_open_closed_no_period_and_is_tenant_scoped(): void
    {
        $guard = $this->app->make(AccountingPeriodPostingGuard::class);
        $date = new PostingDate(new DateTimeImmutable('2026-08-26'));
        $open = $guard->lockForPosting($this->admin(self::A), $date);
        self::assertSame(AccountingPeriodPostingDecisionStatus::Open, $open->status);
        self::assertNotNull($open->bookYearId);
        self::assertNotNull($open->accountingPeriodId);
        self::assertSame(AccountingPeriodPostingDecisionStatus::NoPeriod, $guard->lockForPosting($this->admin(self::B), $date)->status);

        DB::table('accounting_periods')->where('administration_id', self::A)->update(['status' => 'closed']);
        self::assertSame(AccountingPeriodPostingDecisionStatus::Closed, $guard->lockForPosting($this->admin(self::A), $date)->status);
    }

    public function test_guard_fails_typed_on_ambiguous_period_integrity(): void
    {
        $year = DB::table('book_years')->where('administration_id', self::A)->value('id');
        DB::table('accounting_periods')->insert([
            'id' => 'ac240000-0000-4000-8000-000000000099', 'administration_id' => self::A,
            'book_year_id' => $year, 'code' => 'OVERLAP', 'label' => 'Overlap',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $result = $this->app->make(AccountingPeriodPostingGuard::class)->lockForPosting($this->admin(self::A), new PostingDate(new DateTimeImmutable('2026-08-26')));
        self::assertSame(AccountingPeriodPostingDecisionStatus::IntegrityFailure, $result->status);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}
