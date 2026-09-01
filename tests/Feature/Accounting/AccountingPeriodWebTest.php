<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\AccountingPeriodRole;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\Entities\MembershipRole;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\AccountingPeriodAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AccountingPeriodWebTest extends TestCase
{
    use RefreshDatabase;

    private const string USER = 'aa100000-0000-4000-8000-000000000001';

    private const string ADMIN_A = 'aa100000-0000-4000-8000-000000000002';

    private const string ADMIN_B = 'aa100000-0000-4000-8000-000000000003';

    private const string MEMBER_A = 'aa100000-0000-4000-8000-000000000004';

    private const string MEMBER_B = 'aa100000-0000-4000-8000-000000000005';

    protected function setUp(): void
    {
        parent::setUp();
        $user = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($user, new DisplayName('Period User'), new EmailAddress('period@example.test'), 'correct-secure-password');
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMIN_A, 'APA'));
        $administrations->save($this->administration(self::ADMIN_B, 'APB'));
        $memberships = new EloquentAdministrationMembershipRepository;
        $memberships->save($this->membership(self::MEMBER_A, $user, self::ADMIN_A));
        $memberships->save($this->membership(self::MEMBER_B, $user, self::ADMIN_B));
        $this->app->make(AccountingPeriodAuthorizationProvisioner::class)->provision();
        $roles = new EloquentMembershipRoleRepository;
        $roles->save(new MembershipRole(new MembershipRoleId(new Uuid('aa100000-0000-4000-8000-000000000006')), new AdministrationMembershipId(new Uuid(self::MEMBER_A)), AccountingPeriodRole::Manager->id(), true));
        $roles->save(new MembershipRole(new MembershipRoleId(new Uuid('aa100000-0000-4000-8000-000000000007')), new AdministrationMembershipId(new Uuid(self::MEMBER_A)), AccountingPeriodRole::Reopener->id(), true));
        $roles->save(new MembershipRole(new MembershipRoleId(new Uuid('aa100000-0000-4000-8000-000000000008')), new AdministrationMembershipId(new Uuid(self::MEMBER_B)), AccountingPeriodRole::Manager->id(), true));
        $roles->save(new MembershipRole(new MembershipRoleId(new Uuid('aa100000-0000-4000-8000-000000000009')), new AdministrationMembershipId(new Uuid(self::MEMBER_B)), AccountingPeriodRole::Reopener->id(), true));
        $this->post('/login', ['email' => 'period@example.test', 'password' => 'correct-secure-password'])->assertRedirect('/app');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
    }

    public function test_complete_period_management_flow_is_tenant_safe_and_escaped(): void
    {
        $this->get('/settings/accounting-periods')->assertOk()
            ->assertSeeText('Er is nog geen boekjaar ingericht')
            ->assertSeeText('Perioden');

        $this->post('/settings/accounting-periods', [
            'code' => '2026', 'label' => '<script>alert(1)</script>', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'administration_id' => self::ADMIN_B,
        ])->assertRedirect();
        $year = DB::table('book_years')->sole();
        self::assertSame(self::ADMIN_A, $year->administration_id);
        $this->get('/settings/accounting-periods/'.$year->id)->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSeeText('Posting readiness: nog niet gereed');

        $this->post('/settings/accounting-periods', ['code' => '2026', 'label' => 'Dubbel', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31'])
            ->assertSessionHasErrors('book_year');
        $this->post('/settings/accounting-periods', ['code' => 'OVERLAP', 'label' => 'Overlap', 'start_date' => '2026-12-01', 'end_date' => '2027-12-01'])
            ->assertSessionHasErrors('book_year');
        $this->post('/settings/accounting-periods', ['code' => 'BAD', 'label' => 'Bad', 'start_date' => '2026-12-31', 'end_date' => '2026-01-01'])
            ->assertSessionHasErrors('end_date');

        $this->put('/settings/accounting-periods/'.$year->id, ['label' => 'Boekjaar 2026', 'code' => 'CHANGED', 'start_date' => '2000-01-01'])
            ->assertRedirect();
        self::assertSame('2026', DB::table('book_years')->value('code'));
        self::assertSame('2026-01-01', DB::table('book_years')->value('start_date'));
        $this->get('/settings/accounting-periods')->assertOk()->assertSeeText('De periodenindeling is nog niet volledig');

        $this->post('/settings/accounting-periods/'.$year->id.'/periods', [
            'code' => 'P01', 'label' => 'Volledig jaar', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'administration_id' => self::ADMIN_B,
        ])->assertRedirect();
        $period = DB::table('accounting_periods')->sole();
        self::assertSame(self::ADMIN_A, $period->administration_id);
        $this->get('/settings/accounting-periods')->assertOk()->assertSeeText('De periodenindeling is volledig');
        $this->get('/settings/accounting-periods/'.$year->id)->assertOk()
            ->assertSeeText('Nieuwe boekingen met een boekingsdatum in deze periode worden geblokkeerd.');

        $reason = '<img src=x onerror=alert(1)>';
        $this->post("/settings/accounting-periods/{$year->id}/periods/{$period->id}/close", ['reason' => $reason])->assertRedirect();
        self::assertSame('closed', DB::table('accounting_periods')->value('status'));
        $this->get('/settings/accounting-periods/'.$year->id)->assertOk()
            ->assertSeeText('Open → Closed')->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSeeText('Boekingen met een boekingsdatum in deze periode zijn daarna weer toegestaan.');

        $this->post("/settings/accounting-periods/{$year->id}/periods/{$period->id}/reopen", ['reason' => 'Correctie akkoord'])->assertRedirect();
        $this->get('/settings/accounting-periods/'.$year->id)->assertOk()
            ->assertSeeText('Open → Closed')->assertSeeText('Closed → Open');
        self::assertSame(2, DB::table('accounting_period_status_history')->count());

        $this->get('/settings/accounting-periods/not-a-uuid')->assertNotFound();
        $this->get("/settings/accounting-periods/{$year->id}/periods/{$period->id}/close")->assertStatus(405);
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_B]);
        $this->get('/settings/accounting-periods/'.$year->id)->assertNotFound();
        $this->post("/settings/accounting-periods/{$year->id}/periods/{$period->id}/close", ['reason' => 'Cross tenant'])->assertNotFound();
        $this->post("/settings/accounting-periods/{$year->id}/periods/{$period->id}/reopen", ['reason' => 'Cross tenant'])->assertNotFound();
        self::assertSame(1, DB::table('book_years')->count());
    }

    public function test_manager_can_explicitly_replace_an_eligible_plan_with_months(): void
    {
        $this->post('/settings/accounting-periods', ['code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])->assertRedirect();
        $year = DB::table('book_years')->sole();
        $this->post('/settings/accounting-periods/'.$year->id.'/periods', ['code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])->assertRedirect();
        $oldPeriod = DB::table('accounting_periods')->sole();

        $this->get('/settings/accounting-periods/'.$year->id)->assertOk()
            ->assertSeeText('Periodenindeling opnieuw inrichten')
            ->assertSeeText('Financiële boekingen worden niet gewijzigd')
            ->assertSeeText('12 maandperioden genereren');
        $this->get('/settings/accounting-periods/'.$year->id.'/periods/replace-with-months')->assertStatus(405);
        $this->post('/settings/accounting-periods/'.$year->id.'/periods/replace-with-months', [
            'expected_period_ids' => [$oldPeriod->id],
            'administration_id' => self::ADMIN_B,
        ])->assertRedirect('/settings/accounting-periods/'.$year->id);

        $periods = DB::table('accounting_periods')->where('book_year_id', $year->id)->orderBy('start_date')->get();
        self::assertCount(12, $periods);
        self::assertSame(self::ADMIN_A, $periods->first()->administration_id);
        self::assertSame('2026-01-01', $periods->first()->start_date);
        self::assertSame('2026-12-31', $periods->last()->end_date);
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration(new AdministrationId(new Uuid($id)), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function membership(string $id, UserId $user, string $administration): AdministrationMembership
    {
        return new AdministrationMembership(new AdministrationMembershipId(new Uuid($id)), $user, new AdministrationId(new Uuid($administration)), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
    }
}
