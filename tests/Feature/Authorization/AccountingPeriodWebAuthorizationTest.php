<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\AccountingPeriodPermission;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\AccountingPeriodAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AccountingPeriodWebAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const string USER = 'ab100000-0000-4000-8000-000000000001';

    private const string ADMIN = 'ab100000-0000-4000-8000-000000000002';

    private const string MEMBER = 'ab100000-0000-4000-8000-000000000003';

    private const string YEAR = 'ab100000-0000-4000-8000-000000000004';

    private const string PERIOD = 'ab100000-0000-4000-8000-000000000005';

    protected function setUp(): void
    {
        parent::setUp();
        $user = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($user, new DisplayName('Authorization User'), new EmailAddress('ap-auth@example.test'), 'correct-secure-password');
        (new EloquentAdministrationRepository)->save(new Administration(new AdministrationId(new Uuid(self::ADMIN)), new AdministrationCode('APAUTH'), new AdministrationName('AP Auth'), null, new Currency('EUR'), AdministrationStatus::Active));
        (new EloquentAdministrationMembershipRepository)->save(new AdministrationMembership(new AdministrationMembershipId(new Uuid(self::MEMBER)), $user, new AdministrationId(new Uuid(self::ADMIN)), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
        $this->app->make(AccountingPeriodAuthorizationProvisioner::class)->provision();
        $now = now();
        DB::table('book_years')->insert(['id' => self::YEAR, 'administration_id' => self::ADMIN, 'code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('accounting_periods')->insert(['id' => self::PERIOD, 'administration_id' => self::ADMIN, 'book_year_id' => self::YEAR, 'code' => 'P1', 'label' => 'P1', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        $this->post('/login', ['email' => 'ap-auth@example.test', 'password' => 'correct-secure-password'])->assertRedirect('/app');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN]);
    }

    #[DataProvider('permissionMatrix')]
    public function test_permissions_are_independent(AccountingPeriodPermission $permission, array $allowed): void
    {
        $assignment = $this->assignOnly($permission);
        foreach ($this->requests() as $name => [$method, $uri, $data]) {
            $response = $this->{$method}($uri, $data);
            in_array($name, $allowed, true) ? self::assertNotSame(403, $response->getStatusCode(), $name) : $response->assertForbidden();
        }

        DB::table('administration_membership_roles')->where('id', $assignment)->update(['active' => false]);
        $this->get('/settings/accounting-periods')->assertForbidden();
    }

    public static function permissionMatrix(): array
    {
        return [
            'view' => [AccountingPeriodPermission::View, ['index', 'show']],
            'manage' => [AccountingPeriodPermission::Manage, ['create', 'store', 'edit', 'update', 'setup', 'replace']],
            'close' => [AccountingPeriodPermission::Close, ['close']],
            'reopen' => [AccountingPeriodPermission::Reopen, ['reopen']],
        ];
    }

    public function test_inactive_membership_is_denied_before_period_authorization(): void
    {
        $this->assignOnly(AccountingPeriodPermission::View);
        DB::table('administration_memberships')->where('id', self::MEMBER)->update(['active' => false]);
        $this->get('/settings/accounting-periods')->assertRedirect('/administrations/select');
    }

    private function assignOnly(AccountingPeriodPermission $permission): string
    {
        $suffix = str_pad((string) (array_search($permission, AccountingPeriodPermission::cases(), true) + 1), 12, '0', STR_PAD_LEFT);
        $role = 'ab200000-0000-4000-8000-'.$suffix;
        $rolePermission = 'ab300000-0000-4000-8000-'.$suffix;
        $membershipRole = 'ab400000-0000-4000-8000-'.$suffix;
        $now = now();
        DB::table('roles')->insert(['id' => $role, 'code' => 'AP_TEST_'.$permission->name, 'name' => $permission->name, 'description' => null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('role_permissions')->insert(['id' => $rolePermission, 'role_id' => $role, 'permission_id' => $permission->id()->toString(), 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('administration_membership_roles')->insert(['id' => $membershipRole, 'membership_id' => self::MEMBER, 'role_id' => $role, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);

        return $membershipRole;
    }

    private function requests(): array
    {
        return [
            'index' => ['get', '/settings/accounting-periods', []],
            'show' => ['get', '/settings/accounting-periods/'.self::YEAR, []],
            'create' => ['get', '/settings/accounting-periods/create', []],
            'store' => ['post', '/settings/accounting-periods', []],
            'edit' => ['get', '/settings/accounting-periods/'.self::YEAR.'/edit', []],
            'update' => ['put', '/settings/accounting-periods/'.self::YEAR, []],
            'setup' => ['post', '/settings/accounting-periods/'.self::YEAR.'/periods', []],
            'replace' => ['post', '/settings/accounting-periods/'.self::YEAR.'/periods/replace-with-months', []],
            'close' => ['post', '/settings/accounting-periods/'.self::YEAR.'/periods/'.self::PERIOD.'/close', []],
            'reopen' => ['post', '/settings/accounting-periods/'.self::YEAR.'/periods/'.self::PERIOD.'/reopen', []],
        ];
    }
}
