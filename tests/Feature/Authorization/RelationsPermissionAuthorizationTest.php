<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\RelationsRole;
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
use App\Http\Middleware\EnsureRelationsPermission;
use App\Infrastructure\Identity\RelationsAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RelationsPermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const string USER_ID = 'a0000000-0000-4000-8000-000000000001';

    private const string ADMINISTRATION_A = 'a0000000-0000-4000-8000-000000000002';

    private const string ADMINISTRATION_B = 'b0000000-0000-4000-8000-000000000002';

    private const string MEMBERSHIP_A = 'a0000000-0000-4000-8000-000000000003';

    private const string MEMBERSHIP_B = 'b0000000-0000-4000-8000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            'web',
            'auth',
            'domain.active',
            'administration.active',
            EnsureRelationsPermission::using(RelationsPermission::View),
        ])->get('/_test/relations/view', static fn (): string => 'allowed');
        Route::middleware([
            'web',
            'auth',
            'domain.active',
            'administration.active',
            EnsureRelationsPermission::using(RelationsPermission::ClassifySupplier),
        ])->get('/_test/relations/classify-supplier', static fn (): string => 'allowed');
    }

    public function test_viewer_is_allowed_in_its_administration_and_navigation_is_visible(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, 'a1000000-0000-4000-8000-000000000001', self::MEMBERSHIP_A);
        $this->loginWithAdministration(self::ADMINISTRATION_A);

        $this->get('/_test/relations/view')->assertOk()->assertSee('allowed');
        $this->get('/_test/relations/classify-supplier')->assertForbidden();
        $this->get('/app')->assertOk()->assertSee('Relaties')->assertSee('Alle relaties');
    }

    public function test_permission_from_administration_a_does_not_authorize_administration_b(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, 'a1000000-0000-4000-8000-000000000001', self::MEMBERSHIP_A);
        $this->loginWithAdministration(self::ADMINISTRATION_B);

        $this->get('/_test/relations/view')->assertForbidden();
        $this->get('/app')->assertOk()->assertDontSee('Relaties')->assertDontSee('Alle relaties');
    }

    public function test_manager_has_multiple_required_permissions(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Manager, 'a1000000-0000-4000-8000-000000000002', self::MEMBERSHIP_A);
        $this->loginWithAdministration(self::ADMINISTRATION_A);

        $this->get('/_test/relations/view')->assertOk();
        $this->get('/_test/relations/classify-supplier')->assertOk();
    }

    public function test_revoked_role_is_refreshed_on_the_next_request(): void
    {
        $this->provisionScenario();
        $assignmentId = $this->assignRole(RelationsRole::Viewer, 'a1000000-0000-4000-8000-000000000003', self::MEMBERSHIP_A);
        $this->loginWithAdministration(self::ADMINISTRATION_A);
        $this->get('/_test/relations/view')->assertOk();

        $repository = new EloquentMembershipRoleRepository;
        $assignment = $repository->findById($assignmentId);
        self::assertNotNull($assignment);
        $assignment->deactivate();
        $repository->save($assignment);

        $this->get('/_test/relations/view')->assertForbidden();
        $this->get('/app')->assertOk()->assertDontSee('Relaties');
    }

    public function test_inactive_membership_is_rejected_before_permission_middleware(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, 'a1000000-0000-4000-8000-000000000004', self::MEMBERSHIP_A);
        $this->loginWithAdministration(self::ADMINISTRATION_A);

        $memberships = new EloquentAdministrationMembershipRepository;
        $membership = $memberships->findByUserAndAdministration(
            new UserId(new Uuid(self::USER_ID)),
            new AdministrationId(new Uuid(self::ADMINISTRATION_A)),
        );
        self::assertNotNull($membership);
        $membership->deactivate();
        $memberships->save($membership);

        $this->get('/_test/relations/view')->assertRedirect('/administrations/select');
    }

    public function test_guest_and_missing_administration_are_handled_before_permission_denial(): void
    {
        $this->get('/_test/relations/view')->assertRedirect('/login');
        $this->flushSession();

        $this->provisionScenario();
        $this->login();
        $this->get('/_test/relations/view')->assertRedirect('/administrations/select');
    }

    private function provisionScenario(): void
    {
        $userId = new UserId(new Uuid(self::USER_ID));
        $this->app->make(ProvisionUserAccount::class)->execute(
            $userId,
            new DisplayName('Relations User'),
            new EmailAddress('relations@example.com'),
            'correct-secure-password',
        );

        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMINISTRATION_A, 'RELA', 'Relations A'));
        $administrations->save($this->administration(self::ADMINISTRATION_B, 'RELB', 'Relations B'));

        $memberships = new EloquentAdministrationMembershipRepository;
        $memberships->save($this->membership(self::MEMBERSHIP_A, $userId, self::ADMINISTRATION_A));
        $memberships->save($this->membership(self::MEMBERSHIP_B, $userId, self::ADMINISTRATION_B));
        $this->app->make(RelationsAuthorizationProvisioner::class)->provision();
    }

    private function assignRole(RelationsRole $role, string $assignmentId, string $membershipId): MembershipRoleId
    {
        $id = new MembershipRoleId(new Uuid($assignmentId));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(
            $id,
            $this->membershipId($membershipId),
            $role->id(),
            true,
        ));

        return $id;
    }

    private function loginWithAdministration(string $administrationId): void
    {
        $this->login();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => $administrationId]);
    }

    private function login(): void
    {
        $this->post('/login', [
            'email' => 'relations@example.com',
            'password' => 'correct-secure-password',
        ])->assertRedirect('/app');
    }

    private function administration(string $id, string $code, string $name): Administration
    {
        return new Administration(
            new AdministrationId(new Uuid($id)),
            new AdministrationCode($code),
            new AdministrationName($name),
            null,
            new Currency('EUR'),
            AdministrationStatus::Active,
        );
    }

    private function membership(string $id, UserId $userId, string $administrationId): AdministrationMembership
    {
        return new AdministrationMembership(
            $this->membershipId($id),
            $userId,
            new AdministrationId(new Uuid($administrationId)),
            true,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2027-01-01'),
        );
    }

    private function membershipId(string $id): AdministrationMembershipId
    {
        return new AdministrationMembershipId(new Uuid($id));
    }
}
