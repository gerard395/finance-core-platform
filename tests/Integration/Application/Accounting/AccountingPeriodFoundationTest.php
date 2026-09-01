<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Accounting;

use App\Application\Accounting\AccountingPeriodMutationStatus;
use App\Application\Accounting\BookYearRepository;
use App\Application\Accounting\CloseAccountingPeriod;
use App\Application\Accounting\CreateBookYear;
use App\Application\Accounting\ReopenAccountingPeriod;
use App\Application\Identity\AuthorizationReadRepository;
use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Definitions\AccountingPeriodPermission;
use App\Domain\Identity\Definitions\AccountingPeriodRole;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Identity\AccountingPeriodAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationMembershipRoleRecord;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class AccountingPeriodFoundationTest extends TestCase
{
    use RefreshDatabase;

    private AdministrationId $admin;

    private UserId $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new AdministrationId(new Uuid('a9210000-0000-4000-8000-000000000001'));
        $this->actor = new UserId(new Uuid('a9220000-0000-4000-8000-000000000001'));
        $now = now();
        DB::table('administrations')->insert(['id' => $this->admin->toString(), 'code' => 'AP', 'name' => 'AP', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('domain_users')->insert(['id' => $this->actor->toString(), 'display_name' => 'AP Actor', 'email' => 'ap@example.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_authorization_is_exact_idempotent_and_has_no_auto_assignments(): void
    {
        $p = $this->app->make(AccountingPeriodAuthorizationProvisioner::class);
        $p->provision();
        $before = [PermissionRecord::count(), RoleRecord::count(), RolePermissionRecord::count()];
        $p->provision();
        self::assertSame($before, [PermissionRecord::count(), RoleRecord::count(), RolePermissionRecord::count()]);
        self::assertSame(4, PermissionRecord::count());
        self::assertSame(2, RoleRecord::count());
        self::assertSame(5, RolePermissionRecord::count());
        self::assertSame(0, AdministrationMembershipRoleRecord::count());
        self::assertSame(['ACCOUNTING.PERIODS_VIEW', 'ACCOUNTING.PERIODS_MANAGE', 'ACCOUNTING.PERIODS_CLOSE'], array_column(AccountingPeriodRole::Manager->permissions(), 'value'));
        self::assertSame(['ACCOUNTING.PERIODS_VIEW', 'ACCOUNTING.PERIODS_REOPEN'], array_column(AccountingPeriodRole::Reopener->permissions(), 'value'));
    }

    public function test_authorization_is_independent_revocable_and_collision_safe(): void
    {
        $this->app->make(AccountingPeriodAuthorizationProvisioner::class)->provision();
        $now = now();
        DB::table('administration_memberships')->insert(['id' => 'a9250000-0000-4000-8000-000000000001', 'user_id' => $this->actor->toString(), 'administration_id' => $this->admin->toString(), 'active' => true, 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('administration_membership_roles')->insert(['id' => 'a9260000-0000-4000-8000-000000000001', 'membership_id' => 'a9250000-0000-4000-8000-000000000001', 'role_id' => AccountingPeriodRole::Manager->id()->toString(), 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $reader = $this->app->make(AuthorizationReadRepository::class);
        $ids = array_map(fn ($id) => $id->toString(), $reader->effectivePermissionIds($this->actor, $this->admin, new DateTimeImmutable('2026-08-31')));
        self::assertContains(AccountingPeriodPermission::Manage->id()->toString(), $ids);
        self::assertContains(AccountingPeriodPermission::Close->id()->toString(), $ids);
        self::assertNotContains(AccountingPeriodPermission::Reopen->id()->toString(), $ids);
        DB::table('administration_membership_roles')->where('id', 'a9260000-0000-4000-8000-000000000001')->update(['role_id' => AccountingPeriodRole::Reopener->id()->toString()]);
        $ids = array_map(fn ($id) => $id->toString(), $reader->effectivePermissionIds($this->actor, $this->admin, new DateTimeImmutable('2026-08-31')));
        self::assertContains(AccountingPeriodPermission::Reopen->id()->toString(), $ids);
        self::assertNotContains(AccountingPeriodPermission::Manage->id()->toString(), $ids);
        self::assertNotContains(AccountingPeriodPermission::Close->id()->toString(), $ids);
        DB::table('administration_memberships')->where('id', 'a9250000-0000-4000-8000-000000000001')->update(['active' => false]);
        self::assertSame([], $reader->effectivePermissionIds($this->actor, $this->admin, new DateTimeImmutable('2026-08-31')));

        PermissionRecord::query()->whereKey(AccountingPeriodPermission::View->id()->toString())->update(['code' => 'COLLISION']);
        $this->expectException(LogicException::class);
        $this->app->make(AccountingPeriodAuthorizationProvisioner::class)->provision();
    }

    public function test_roundtrip_close_reopen_history_and_historical_readiness(): void
    {
        $yid = new BookYearId(new Uuid('a9230000-0000-4000-8000-000000000001'));
        $pid = new AccountingPeriodId(new Uuid('a9240000-0000-4000-8000-000000000001'));
        $y = new BookYear($yid, $this->admin, 'FY26', 'FY 26', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'), [new AccountingPeriod($pid, $this->admin, $yid, 'P1', '2026', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'))]);
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(CreateBookYear::class)->execute($y));
        $round = $this->app->make(BookYearRepository::class)->find($this->admin, $yid);
        self::assertNotNull($round);
        self::assertTrue($round->hasFullCoverage());
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(CloseAccountingPeriod::class)->execute($this->admin, $pid, 'close', $this->actor, new DateTimeImmutable('2026-08-31')));
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->app->make(ReopenAccountingPeriod::class)->execute($this->admin, $pid, 'reopen', $this->actor, new DateTimeImmutable('2026-09-01')));
        self::assertSame(2, DB::table('accounting_period_status_history')->count());
        self::assertSame('open', DB::table('accounting_periods')->value('status'));
        self::assertSame([], $this->app->make(BookYearRepository::class)->uncoveredPostingDates($this->admin));
    }

    public function test_overlapping_year_is_rejected_but_gap_is_allowed(): void
    {
        $this->create('a9230000-0000-4000-8000-000000000011', '2026-01-01', '2026-06-30');
        self::assertSame(AccountingPeriodMutationStatus::IntegrityFailure, $this->create('a9230000-0000-4000-8000-000000000012', '2026-06-01', '2026-12-31'));
        self::assertSame(AccountingPeriodMutationStatus::Success, $this->create('a9230000-0000-4000-8000-000000000013', '2027-01-01', '2027-12-31'));
    }

    private function create(string $id, string $start, string $end): AccountingPeriodMutationStatus
    {
        $yid = new BookYearId(new Uuid($id));
        $pid = new AccountingPeriodId(new Uuid(str_replace('a923', 'a924', $id)));
        $y = new BookYear($yid, $this->admin, $id, $id, new DateTimeImmutable($start), new DateTimeImmutable($end), [new AccountingPeriod($pid, $this->admin, $yid, 'P', 'P', new DateTimeImmutable($start), new DateTimeImmutable($end))]);

        return $this->app->make(CreateBookYear::class)->execute($y);
    }
}
