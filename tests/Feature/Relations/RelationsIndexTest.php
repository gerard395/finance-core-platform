<?php

declare(strict_types=1);

namespace Tests\Feature\Relations;

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
use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\Entities\RolePermission;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName as UserDisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Identity\ValueObjects\RolePermissionId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Entities\Supplier;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\RelationsAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentCustomerRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSupplierRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RelationsIndexTest extends TestCase
{
    use RefreshDatabase;

    private const string USER = 'a0000000-0000-4000-8000-000000000001';

    private const string ADMIN_A = 'a0000000-0000-4000-8000-000000000002';

    private const string ADMIN_B = 'b0000000-0000-4000-8000-000000000002';

    private const string MEMBERSHIP_A = 'a0000000-0000-4000-8000-000000000003';

    private const string MEMBERSHIP_B = 'b0000000-0000-4000-8000-000000000003';

    public function test_access_requires_authentication_active_administration_and_view_permission(): void
    {
        $this->get('/relations')->assertRedirect('/login');
        $this->provisionScenario();
        $this->login();
        $this->get('/relations')->assertRedirect('/administrations/select');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
        $this->get('/relations')->assertForbidden();
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 1);
        $this->get('/relations')->assertOk()->assertSeeText('Relaties');
    }

    public function test_create_or_update_without_view_does_not_authorize_index(): void
    {
        $this->provisionScenario();
        $createAssignment = $this->assignPermissionOnly(RelationsPermission::Create, 1);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->get('/relations')->assertForbidden();
        $assignment = (new EloquentMembershipRoleRepository)->findById($createAssignment);
        self::assertNotNull($assignment);
        $assignment->deactivate();
        (new EloquentMembershipRoleRepository)->save($assignment);
        $this->assignPermissionOnly(RelationsPermission::Update, 2);
        $this->get('/relations')->assertForbidden();
    }

    public function test_revoked_view_is_denied_on_the_next_request(): void
    {
        $this->provisionScenario();
        $assignmentId = $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 2);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->get('/relations')->assertOk();
        $repository = new EloquentMembershipRoleRepository;
        $assignment = $repository->findById($assignmentId);
        self::assertNotNull($assignment);
        $assignment->deactivate();
        $repository->save($assignment);
        $this->get('/relations')->assertForbidden();
    }

    public function test_index_renders_all_classification_and_status_states_without_fake_links(): void
    {
        $this->authorizedScenario();
        $customer = $this->relation(self::ADMIN_A, 1, 'C-001', '<Customer>', true);
        $supplier = $this->relation(self::ADMIN_A, 2, 'S-001', 'Supplier', false);
        $both = $this->relation(self::ADMIN_A, 3, 'B-001', 'Both', true);
        $this->relation(self::ADMIN_A, 4, 'N-001', 'Neither', true);
        $this->customer(self::ADMIN_A, $customer, 1);
        $this->supplier(self::ADMIN_A, $supplier, 2);
        $this->customer(self::ADMIN_A, $both, 3);
        $this->supplier(self::ADMIN_A, $both, 3);

        $this->get('/relations')->assertOk()
            ->assertSee('Relaties')->assertSee('C-001')->assertSee('&lt;Customer&gt;', false)
            ->assertDontSee('<Customer>', false)->assertSeeText('Klant')->assertSeeText('Leverancier')
            ->assertSeeText('Actief')->assertSeeText('Inactief')->assertSeeText('Geen classificatie')
            ->assertDontSee('Nieuwe relatie')->assertDontSee('href="#"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_search_filters_sorting_and_safe_validation_are_applied(): void
    {
        $this->authorizedScenario();
        $alpha = $this->relation(self::ADMIN_A, 1, 'Z-200', 'Alpha Customer', true);
        $beta = $this->relation(self::ADMIN_A, 2, 'A-100', 'Beta Supplier', false);
        $both = $this->relation(self::ADMIN_A, 3, 'M-150', 'Gamma Both', true);
        $this->relation(self::ADMIN_A, 4, 'N-400', 'Delta Neither', true);
        $this->customer(self::ADMIN_A, $alpha, 1);
        $this->supplier(self::ADMIN_A, $beta, 2);
        $this->customer(self::ADMIN_A, $both, 3);
        $this->supplier(self::ADMIN_A, $both, 3);

        $this->get('/relations?q=Z-200')->assertSeeText('Alpha Customer')->assertDontSeeText('Beta Supplier');
        $this->get('/relations?q=Supplier')->assertSeeText('A-100')->assertDontSeeText('Z-200');
        $this->get('/relations?q=missing')->assertSeeText('Geen relaties gevonden voor deze zoekopdracht.');
        foreach (['customer' => 'Z-200', 'supplier' => 'A-100', 'both' => 'M-150', 'neither' => 'N-400'] as $filter => $code) {
            $this->get('/relations?classification='.$filter)->assertSeeText($code);
        }
        $this->get('/relations?status=active')->assertDontSeeText('A-100');
        $this->get('/relations?status=inactive')->assertSeeText('A-100')->assertDontSeeText('Z-200');
        $this->assertOrder('/relations?sort=display_name&direction=asc', 'Alpha Customer', 'Beta Supplier');
        $this->assertOrder('/relations?sort=display_name&direction=desc', 'Gamma Both', 'Delta Neither');
        $this->assertOrder('/relations?sort=code&direction=asc', 'A-100', 'M-150');
        $this->assertOrder('/relations?sort=status&direction=desc', 'Z-200', 'A-100');
        $this->get('/relations?sort=id%60%20desc')->assertSessionHasErrors('sort');
        $this->get('/relations?classification=invalid')->assertSessionHasErrors('classification');
        $this->get('/relations?per_page=10000')->assertSessionHasErrors('per_page');
        $this->get('/relations?q=%25%27%3B%20OR%201%3D1%20--')->assertOk()->assertSeeText('Geen relaties gevonden');
    }

    public function test_pagination_uses_25_by_default_and_retains_query_parameters(): void
    {
        $this->authorizedScenario();
        for ($sequence = 1; $sequence <= 27; $sequence++) {
            $this->relation(self::ADMIN_A, $sequence, sprintf('P-%02d', $sequence), sprintf('Paged %02d', $sequence), true);
        }
        $first = $this->get('/relations?q=Paged&classification=all&status=active&sort=code&direction=asc&per_page=25');
        $first->assertOk()->assertSeeText('Pagina 1 van 2 · 27 resultaten')->assertSeeText('P-25')->assertDontSeeText('P-26')
            ->assertSee('q=Paged', false)->assertSee('classification=all', false)->assertSee('status=active', false)
            ->assertSee('sort=code', false)->assertSee('direction=asc', false)->assertSee('per_page=25', false)->assertSee('page=2', false);
        $this->get('/relations?q=Paged&classification=all&status=active&sort=code&direction=asc&per_page=25&page=2')
            ->assertSeeText('Pagina 2 van 2 · 27 resultaten')->assertSeeText('P-26')->assertSeeText('P-27')->assertSee('rel="prev"', false);
    }

    public function test_active_administration_is_the_only_tenant_source_and_navigation_is_permission_scoped(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 3);
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_B, 4);
        $this->relation(self::ADMIN_A, 1, 'A-001', 'Tenant Alpha', true);
        $this->relation(self::ADMIN_B, 2, 'B-001', 'Tenant Beta', true);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->get('/relations?administration_id='.self::ADMIN_B)->assertSeeText('Tenant Alpha')->assertDontSeeText('Tenant Beta');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_B]);
        $this->get('/relations')->assertSeeText('Tenant Beta')->assertDontSeeText('Tenant Alpha');

        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
        $this->get('/app')->assertSee('href="'.route('relations.index').'"', false)->assertSeeText('Alle relaties');
        $assignment = (new EloquentMembershipRoleRepository)->findById(new MembershipRoleId($this->uuid('7', 3)));
        self::assertNotNull($assignment);
        $assignment->deactivate();
        (new EloquentMembershipRoleRepository)->save($assignment);
        $this->get('/app')->assertDontSeeText('Alle relaties');
        $this->get('/relations')->assertForbidden();
    }

    public function test_empty_states_are_distinct(): void
    {
        $this->authorizedScenario();
        $this->get('/relations')->assertSeeText('Nog geen relaties.');
        $this->get('/relations?q=anything')->assertSeeText('Geen relaties gevonden voor deze zoekopdracht.');
    }

    public function test_detail_access_requires_authentication_active_administration_and_view(): void
    {
        $relationId = $this->uuid('6', 1)->toString();
        $this->get('/relations/'.$relationId)->assertRedirect('/login');
        $this->provisionScenario();
        $this->login();
        $this->get('/relations/'.$relationId)->assertRedirect('/administrations/select');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
        $this->get('/relations/'.$relationId)->assertForbidden();
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 1);
        $this->relation(self::ADMIN_A, 1, 'D-001', 'Detail Relation', true);
        $this->get('/relations/'.$relationId)->assertOk()->assertSeeText('Detail Relation');
    }

    public function test_non_view_permissions_do_not_authorize_detail(): void
    {
        $this->provisionScenario();
        $this->loginWithAdministration(self::ADMIN_A);
        foreach ([RelationsPermission::Create, RelationsPermission::Update, RelationsPermission::ClassifyCustomer, RelationsPermission::ClassifySupplier] as $sequence => $permission) {
            $assignmentId = $this->assignPermissionOnly($permission, $sequence + 1);
            $this->get('/relations/'.$this->uuid('6', 1)->toString())->assertForbidden();
            $assignment = (new EloquentMembershipRoleRepository)->findById($assignmentId);
            self::assertNotNull($assignment);
            $assignment->deactivate();
            (new EloquentMembershipRoleRepository)->save($assignment);
        }
    }

    public function test_detail_returns_the_same_safe_not_found_for_unknown_cross_tenant_and_malformed_ids(): void
    {
        $this->authorizedScenario();
        $otherTenant = $this->relation(self::ADMIN_B, 2, 'B-002', 'Secret Tenant Relation', true);
        $unknown = $this->uuid('6', 99)->toString();

        $unknownResponse = $this->get('/relations/'.$unknown)->assertNotFound()->assertDontSeeText('Invalid UUID');
        $crossTenantResponse = $this->get('/relations/'.$otherTenant->toString())->assertNotFound()->assertDontSeeText('Secret Tenant Relation');
        $malformedResponse = $this->get('/relations/not-a-uuid')->assertNotFound()->assertDontSeeText('Invalid UUID');
        self::assertSame($unknownResponse->getContent(), $crossTenantResponse->getContent());
        self::assertSame($unknownResponse->getContent(), $malformedResponse->getContent());
    }

    public function test_detail_renders_persisted_fields_status_and_current_classifications_only(): void
    {
        $this->authorizedScenario();
        $customer = $this->relation(self::ADMIN_A, 1, 'C-001', '<Customer Detail>', true);
        $supplier = $this->relation(self::ADMIN_A, 2, 'S-001', 'Supplier Detail', false);
        $both = $this->relation(self::ADMIN_A, 3, 'B-001', 'Both Detail', true);
        $neither = $this->relation(self::ADMIN_A, 4, 'N-001', 'Neither Detail', true);
        $inactiveClassifications = $this->relation(self::ADMIN_A, 5, 'I-001', 'Inactive Classifications', true);
        $this->customer(self::ADMIN_A, $customer, 1);
        $this->supplier(self::ADMIN_A, $supplier, 2);
        $this->customer(self::ADMIN_A, $both, 3);
        $this->supplier(self::ADMIN_A, $both, 3);
        $this->customer(self::ADMIN_A, $inactiveClassifications, 5, false);
        $this->supplier(self::ADMIN_A, $inactiveClassifications, 5, false);

        $this->get('/relations/'.$customer->toString())->assertOk()->assertSee('&lt;Customer Detail&gt;', false)->assertDontSee('<Customer Detail>', false)->assertSeeText('C-001')->assertSeeText('Actief')->assertSeeText('Klant');
        $this->get('/relations/'.$supplier->toString())->assertOk()->assertSeeText('Supplier Detail')->assertSeeText('Inactief')->assertSeeText('Leverancier');
        $this->get('/relations/'.$both->toString())->assertOk()->assertSeeText('Klant')->assertSeeText('Leverancier');
        $this->get('/relations/'.$neither->toString())->assertOk()->assertSeeText('Geen classificatie');
        $this->get('/relations/'.$inactiveClassifications->toString())->assertOk()->assertSeeText('Geen classificatie')->assertDontSee('>Klant<', false)->assertDontSee('>Leverancier<', false);
    }

    public function test_index_has_real_desktop_and_mobile_detail_links(): void
    {
        $this->authorizedScenario();
        $relationId = $this->relation(self::ADMIN_A, 1, 'L-001', 'Linked Relation', true);
        $url = route('relations.show', $relationId->toString());

        $this->get('/relations')->assertOk()->assertSee('href="'.$url.'"', false, 2)
            ->assertSee('aria-label="Bekijk Linked Relation"', false, 2)
            ->assertDontSee('href="#"', false);
    }

    public function test_administration_switch_changes_detail_access(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 3);
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_B, 4);
        $relationA = $this->relation(self::ADMIN_A, 1, 'A-001', 'Tenant Alpha Detail', true);
        $relationB = $this->relation(self::ADMIN_B, 2, 'B-001', 'Tenant Beta Detail', true);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->get('/relations/'.$relationA->toString())->assertOk();
        $this->get('/relations/'.$relationB->toString())->assertNotFound();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_B]);
        $this->get('/relations/'.$relationA->toString())->assertNotFound();
        $this->get('/relations/'.$relationB->toString())->assertOk()->assertSeeText('Tenant Beta Detail');
    }

    private function authorizedScenario(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 9);
        $this->loginWithAdministration(self::ADMIN_A);
    }

    private function provisionScenario(): void
    {
        $userId = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($userId, new UserDisplayName('Relations User'), new EmailAddress('relations@example.com'), 'correct-secure-password');
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMIN_A, 'RELA', 'Relations A'));
        $administrations->save($this->administration(self::ADMIN_B, 'RELB', 'Relations B'));
        $memberships = new EloquentAdministrationMembershipRepository;
        $memberships->save($this->membership(self::MEMBERSHIP_A, $userId, self::ADMIN_A));
        $memberships->save($this->membership(self::MEMBERSHIP_B, $userId, self::ADMIN_B));
        $this->app->make(RelationsAuthorizationProvisioner::class)->provision();
    }

    private function assignRole(RelationsRole $role, string $membershipId, int $sequence): MembershipRoleId
    {
        $id = new MembershipRoleId($this->uuid('7', $sequence));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole($id, new AdministrationMembershipId(new Uuid($membershipId)), $role->id(), true));

        return $id;
    }

    private function assignPermissionOnly(RelationsPermission $permission, int $sequence): MembershipRoleId
    {
        $roleId = new RoleId($this->uuid('8', $sequence));
        (new EloquentRoleRepository)->save(new Role($roleId, new RoleCode('ONLY_'.$permission->name), new RoleName('Only '.$permission->name), null, RoleStatus::Active));
        (new EloquentRolePermissionRepository)->save(new RolePermission(new RolePermissionId($this->uuid('9', $sequence)), $roleId, $permission->id(), true));
        $assignmentId = new MembershipRoleId($this->uuid('7', $sequence));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole($assignmentId, new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $roleId, true));

        return $assignmentId;
    }

    private function relation(string $administrationId, int $sequence, string $code, string $name, bool $active): RelationId
    {
        $id = new RelationId($this->uuid('6', $sequence));
        (new EloquentRelationRepository)->save(new AdministrationId(new Uuid($administrationId)), new Relation($id, new RelationCode($code), new DisplayName($name), $active));

        return $id;
    }

    private function customer(string $administrationId, RelationId $relationId, int $sequence, bool $active = true): void
    {
        (new EloquentCustomerRepository)->save(new AdministrationId(new Uuid($administrationId)), new Customer(new CustomerId($this->uuid('4', $sequence)), $relationId, new CustomerNumber(sprintf('C-%03d', $sequence)), $active));
    }

    private function supplier(string $administrationId, RelationId $relationId, int $sequence, bool $active = true): void
    {
        (new EloquentSupplierRepository)->save(new AdministrationId(new Uuid($administrationId)), new Supplier(new SupplierId($this->uuid('5', $sequence)), $relationId, new SupplierNumber(sprintf('S-%03d', $sequence)), $active));
    }

    private function loginWithAdministration(string $administrationId): void
    {
        $this->login();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => $administrationId]);
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'relations@example.com', 'password' => 'correct-secure-password'])->assertRedirect();
    }

    private function administration(string $id, string $code, string $name): Administration
    {
        return new Administration(new AdministrationId(new Uuid($id)), new AdministrationCode($code), new AdministrationName($name), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function membership(string $id, UserId $userId, string $administrationId): AdministrationMembership
    {
        return new AdministrationMembership(new AdministrationMembershipId(new Uuid($id)), $userId, new AdministrationId(new Uuid($administrationId)), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
    }

    private function uuid(string $prefix, int $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $sequence));
    }

    private function assertOrder(string $uri, string $first, string $second): void
    {
        $content = $this->get($uri)->assertOk()->getContent();
        self::assertIsString($content);
        self::assertLessThan(strpos($content, $second), strpos($content, $first));
    }
}
