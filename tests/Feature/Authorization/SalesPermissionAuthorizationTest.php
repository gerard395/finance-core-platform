<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\RelationsRole;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\Definitions\SalesRole;
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
use App\Http\Middleware\EnsureSalesPermission;
use App\Infrastructure\Identity\RelationsAuthorizationProvisioner;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SalesPermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const string USER_ID = 'c0000000-0000-4000-8000-000000000001';

    private const string ADMIN_A = 'c0000000-0000-4000-8000-000000000002';

    private const string ADMIN_B = 'd0000000-0000-4000-8000-000000000002';

    private const string MEMBERSHIP_A = 'c0000000-0000-4000-8000-000000000003';

    private const string MEMBERSHIP_B = 'd0000000-0000-4000-8000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([SalesPermission::View, SalesPermission::IssueInvoices, SalesPermission::PostInvoices] as $permission) {
            Route::middleware(['web', 'auth', 'domain.active', 'administration.active', EnsureSalesPermission::using($permission)])
                ->get('/_test/sales/'.$permission->name, static fn (): string => 'allowed');
        }
    }

    #[DataProvider('leastPrivilegeProvider')]
    public function test_editor_manager_and_poster_enforce_exact_least_privilege(
        SalesRole $role,
        bool $view,
        bool $issue,
        bool $post,
        string $assignmentId,
    ): void {
        $this->provisionScenario();
        $this->assignRole($role, $assignmentId);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->assertAccess(SalesPermission::View, $view);
        $this->assertAccess(SalesPermission::IssueInvoices, $issue);
        $this->assertAccess(SalesPermission::PostInvoices, $post);
    }

    public static function leastPrivilegeProvider(): array
    {
        return [
            'editor' => [SalesRole::Editor, true, false, false, 'c1000000-0000-4000-8000-000000000001'],
            'manager' => [SalesRole::Manager, true, true, false, 'c1000000-0000-4000-8000-000000000002'],
            'poster' => [SalesRole::Poster, false, false, true, 'c1000000-0000-4000-8000-000000000003'],
        ];
    }

    public function test_permission_is_tenant_scoped_and_revocation_applies_next_request(): void
    {
        $this->provisionScenario();
        $assignment = $this->assignRole(SalesRole::Viewer, 'c1000000-0000-4000-8000-000000000010');

        $this->loginWithAdministration(self::ADMIN_B);
        $this->get($this->path(SalesPermission::View))->assertForbidden();

        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
        $this->get($this->path(SalesPermission::View))->assertOk();

        $repository = new EloquentMembershipRoleRepository;
        $membershipRole = $repository->findById($assignment);
        self::assertNotNull($membershipRole);
        $membershipRole->deactivate();
        $repository->save($membershipRole);
        $this->get($this->path(SalesPermission::View))->assertForbidden();
    }

    public function test_dashboard_sales_navigation_uses_effective_tenant_permissions_and_remains_independent_from_relations(): void
    {
        $this->provisionScenario();
        $this->app->make(RelationsAuthorizationProvisioner::class)->provision();
        $salesAssignment = $this->assignRole(SalesRole::Viewer, 'c1000000-0000-4000-8000-000000000020');
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(
            new MembershipRoleId(new Uuid('c1000000-0000-4000-8000-000000000021')),
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)),
            RelationsRole::Viewer->id(),
            true,
        ));
        $this->loginWithAdministration(self::ADMIN_A);

        $dashboard = $this->get('/app')->assertOk();
        foreach (['sales.quotations.index' => 'Offertes', 'sales.orders.index' => 'Orders', 'sales.invoices.index' => 'Facturen', 'sales.credit-invoices.index' => 'Creditfacturen'] as $route => $label) {
            $dashboard->assertSee('href="'.route($route).'"', false)->assertSeeText($label);
        }
        $dashboard->assertSee('href="'.route('relations.index').'"', false)->assertSeeText('Alle relaties');

        $roles = new EloquentMembershipRoleRepository;
        $sales = $roles->findById($salesAssignment);
        self::assertNotNull($sales);
        $sales->deactivate();
        $roles->save($sales);
        $withoutSales = $this->get('/app')->assertOk()->assertSeeText('Alle relaties');
        foreach (['Offertes', 'Orders', 'Facturen', 'Creditfacturen'] as $label) {
            $withoutSales->assertDontSeeText($label);
        }

        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_B]);
        $tenantB = $this->get('/app')->assertOk()->assertDontSeeText('Alle relaties');
        foreach (['Offertes', 'Orders', 'Facturen', 'Creditfacturen'] as $label) {
            $tenantB->assertDontSeeText($label);
        }
    }

    public function test_inactive_membership_is_rejected_before_sales_permission(): void
    {
        $this->provisionScenario();
        $this->assignRole(SalesRole::Viewer, 'c1000000-0000-4000-8000-000000000011');
        $this->loginWithAdministration(self::ADMIN_A);

        $repository = new EloquentAdministrationMembershipRepository;
        $membership = $repository->findByUserAndAdministration(new UserId(new Uuid(self::USER_ID)), new AdministrationId(new Uuid(self::ADMIN_A)));
        self::assertNotNull($membership);
        $membership->deactivate();
        $repository->save($membership);

        $this->get($this->path(SalesPermission::View))->assertRedirect('/administrations/select');
    }

    private function assertAccess(SalesPermission $permission, bool $allowed): void
    {
        $response = $this->get($this->path($permission));
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    private function provisionScenario(): void
    {
        $userId = new UserId(new Uuid(self::USER_ID));
        $this->app->make(ProvisionUserAccount::class)->execute($userId, new DisplayName('Sales User'), new EmailAddress('sales@example.com'), 'correct-secure-password');
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMIN_A, 'SALA'));
        $administrations->save($this->administration(self::ADMIN_B, 'SALB'));
        $memberships = new EloquentAdministrationMembershipRepository;
        $memberships->save($this->membership(self::MEMBERSHIP_A, $userId, self::ADMIN_A));
        $memberships->save($this->membership(self::MEMBERSHIP_B, $userId, self::ADMIN_B));
        $this->app->make(SalesAuthorizationProvisioner::class)->provision();
    }

    private function assignRole(SalesRole $role, string $id): MembershipRoleId
    {
        $assignmentId = new MembershipRoleId(new Uuid($id));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole($assignmentId, new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $role->id(), true));

        return $assignmentId;
    }

    private function loginWithAdministration(string $administrationId): void
    {
        $this->post('/login', ['email' => 'sales@example.com', 'password' => 'correct-secure-password'])->assertRedirect('/app');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => $administrationId]);
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration(new AdministrationId(new Uuid($id)), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function membership(string $id, UserId $userId, string $administrationId): AdministrationMembership
    {
        return new AdministrationMembership(new AdministrationMembershipId(new Uuid($id)), $userId, new AdministrationId(new Uuid($administrationId)), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
    }

    private function path(SalesPermission $permission): string
    {
        return '/_test/sales/'.$permission->name;
    }
}
