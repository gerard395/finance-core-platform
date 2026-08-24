<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\AdministrationRole;
use App\Domain\Identity\Definitions\RelationsRole;
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
use App\Infrastructure\Identity\AdministrationAuthorizationProvisioner;
use App\Infrastructure\Identity\RelationsAuthorizationProvisioner;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdministrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string USER_ID = 'a1000000-0000-4000-8000-000000000001';

    private const string ADMIN_A = 'a1000000-0000-4000-8000-000000000002';

    private const string ADMIN_B = 'b1000000-0000-4000-8000-000000000002';

    private const string MEMBERSHIP_A = 'a1000000-0000-4000-8000-000000000003';

    private const string MEMBERSHIP_B = 'b1000000-0000-4000-8000-000000000003';

    private const string ROLE_ASSIGNMENT = 'a1000000-0000-4000-8000-000000000004';

    public function test_permission_reads_and_updates_only_active_administration_settings(): void
    {
        $this->provisionScenario(true);
        $this->login(self::ADMIN_A);

        $this->get('/settings/administration')
            ->assertOk()
            ->assertSee('Administratie A')
            ->assertDontSee('Geheime omschrijving B')
            ->assertSee('href="'.route('settings.administration.edit').'"', false);

        $this->put('/settings/administration', [
            'name' => 'Nieuwe & Veilige Naam',
            'description' => '<script>alert(1)</script>',
            'administration_id' => self::ADMIN_B,
            'code' => 'HACKED',
            'status' => 'inactive',
            'base_currency' => 'USD',
            'vat_identification_number' => ' nl123456789b01 ',
            'fiscal_jurisdiction' => 'be',
        ])->assertRedirect(route('settings.administration.edit'));

        $this->assertDatabaseHas('administrations', [
            'id' => self::ADMIN_A, 'name' => 'Nieuwe & Veilige Naam',
            'description' => '<script>alert(1)</script>', 'code' => 'ADMA',
            'status' => 'active', 'base_currency' => 'EUR',
            'organisation_vat_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'BE',
        ]);
        $this->assertDatabaseHas('administrations', [
            'id' => self::ADMIN_B, 'name' => 'Administratie B',
            'description' => 'Geheime omschrijving B',
        ]);
        $this->get('/settings/administration')->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_unrelated_permission_does_not_grant_access_or_navigation(): void
    {
        $this->provisionScenario(false);
        $this->app->make(SalesAuthorizationProvisioner::class)->provision();
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(
            new MembershipRoleId(new Uuid('a1000000-0000-4000-8000-000000000005')),
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)),
            SalesRole::Viewer->id(), true,
        ));
        $this->login(self::ADMIN_A);

        $this->get('/settings/administration')->assertForbidden();
        $this->put('/settings/administration', ['name' => 'Denied', 'description' => null])->assertForbidden();
        $this->put('/settings/administration/sales-posting', [])->assertForbidden();
        $this->get('/app')->assertOk()->assertDontSee('href="'.route('settings.administration.edit').'"', false);
    }

    public function test_settings_navigation_is_request_scoped_on_dashboard_relations_and_sales_pages(): void
    {
        $this->provisionScenario(true);
        $this->app->make(RelationsAuthorizationProvisioner::class)->provision();
        $this->app->make(SalesAuthorizationProvisioner::class)->provision();
        $roles = new EloquentMembershipRoleRepository;
        $roles->save(new MembershipRole(
            new MembershipRoleId(new Uuid('a1000000-0000-4000-8000-000000000006')),
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), RelationsRole::Viewer->id(), true,
        ));
        $roles->save(new MembershipRole(
            new MembershipRoleId(new Uuid('a1000000-0000-4000-8000-000000000007')),
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), SalesRole::Viewer->id(), true,
        ));
        $this->login(self::ADMIN_A);

        foreach (['/app', '/relations', '/sales/quotations'] as $path) {
            $this->get($path)->assertOk()->assertSee('href="'.route('settings.administration.edit').'"', false);
        }
    }

    public function test_permission_is_tenant_scoped_and_revocation_applies_on_next_request(): void
    {
        $this->provisionScenario(true);
        $this->login(self::ADMIN_B);
        $this->get('/settings/administration')->assertForbidden();

        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
        $this->get('/settings/administration')->assertOk();

        $roles = new EloquentMembershipRoleRepository;
        $assignment = $roles->findById(new MembershipRoleId(new Uuid(self::ROLE_ASSIGNMENT)));
        self::assertNotNull($assignment);
        $assignment->deactivate();
        $roles->save($assignment);
        $this->get('/settings/administration')->assertForbidden();
        $this->put('/settings/administration/sales-posting', [])->assertForbidden();
    }

    public function test_inactive_membership_is_denied_before_settings_authorization(): void
    {
        $this->provisionScenario(true);
        $this->login(self::ADMIN_A);
        $memberships = new EloquentAdministrationMembershipRepository;
        $membership = $memberships->findByUserAndAdministration(
            new UserId(new Uuid(self::USER_ID)), new AdministrationId(new Uuid(self::ADMIN_A)),
        );
        self::assertNotNull($membership);
        $membership->deactivate();
        $memberships->save($membership);

        $this->get('/settings/administration')->assertRedirect('/administrations/select');
        $this->put('/settings/administration/sales-posting', [])->assertRedirect('/administrations/select');
    }

    public function test_validation_is_safe_and_does_not_persist_invalid_input(): void
    {
        $this->provisionScenario(true);
        $this->login(self::ADMIN_A);

        $this->from('/settings/administration')->put('/settings/administration', [
            'name' => ' x', 'description' => str_repeat('x', 1001),
            'vat_identification_number' => 'NL 123', 'fiscal_jurisdiction' => 'NLD',
        ])->assertRedirect('/settings/administration')->assertSessionHasErrors(['name', 'description', 'vat_identification_number', 'fiscal_jurisdiction']);
        $this->assertDatabaseHas('administrations', ['id' => self::ADMIN_A, 'name' => 'Administratie A']);
    }

    private function provisionScenario(bool $assignRole): void
    {
        $userId = new UserId(new Uuid(self::USER_ID));
        $this->app->make(ProvisionUserAccount::class)->execute(
            $userId, new DisplayName('Settings User'), new EmailAddress('settings@example.com'), 'correct-secure-password',
        );
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMIN_A, 'ADMA', 'Administratie A', 'Omschrijving A'));
        $administrations->save($this->administration(self::ADMIN_B, 'ADMB', 'Administratie B', 'Geheime omschrijving B'));
        $memberships = new EloquentAdministrationMembershipRepository;
        $memberships->save($this->membership(self::MEMBERSHIP_A, $userId, self::ADMIN_A));
        $memberships->save($this->membership(self::MEMBERSHIP_B, $userId, self::ADMIN_B));
        $this->app->make(AdministrationAuthorizationProvisioner::class)->provision();

        if ($assignRole) {
            (new EloquentMembershipRoleRepository)->save(new MembershipRole(
                new MembershipRoleId(new Uuid(self::ROLE_ASSIGNMENT)),
                new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)),
                AdministrationRole::Manager->id(), true,
            ));
        }
    }

    private function login(string $administrationId): void
    {
        $this->post('/login', ['email' => 'settings@example.com', 'password' => 'correct-secure-password'])->assertRedirect('/app');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => $administrationId]);
    }

    private function administration(string $id, string $code, string $name, string $description): Administration
    {
        return new Administration(new AdministrationId(new Uuid($id)), new AdministrationCode($code), new AdministrationName($name), $description, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function membership(string $id, UserId $userId, string $administrationId): AdministrationMembership
    {
        return new AdministrationMembership(new AdministrationMembershipId(new Uuid($id)), $userId, new AdministrationId(new Uuid($administrationId)), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
    }
}
