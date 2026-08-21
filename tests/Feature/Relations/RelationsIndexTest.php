<?php

declare(strict_types=1);

namespace Tests\Feature\Relations;

use App\Application\Identity\ProvisionUserAccount;
use App\Application\Relations\AddressWriteResult;
use App\Application\Relations\ContactWriteResult;
use App\Application\Relations\CreateAddress;
use App\Application\Relations\CreateContact;
use App\Application\Relations\RelationNumberSequenceProvisioner;
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
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\EmailAddress as ContactEmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;
use App\Domain\Relations\ValueObjects\PostalCode;
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
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_relation_detail_renders_contact_section_empty_state_status_and_escaped_values(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Editor, self::MEMBERSHIP_A, 31);
        $this->loginWithAdministration(self::ADMIN_A);
        $relationId = $this->relation(self::ADMIN_A, 31, 'CONTACT-31', 'Contact relation', true);

        $this->get(route('relations.show', $relationId->toString()))->assertOk()->assertSeeText('Contactpersonen')->assertSeeText('Nog geen contactpersonen.')->assertSeeText('Contactpersoon toevoegen');
        $contactId = $this->contact($relationId, 31, '<Contact>', 'safe@example.com', '+31 20 123 4567');
        $this->delete(route('relations.contacts.deactivate', [$relationId->toString(), $contactId->toString()]))->assertRedirect(route('relations.show', $relationId->toString()));
        $this->get(route('relations.show', $relationId->toString()))->assertOk()->assertSee('&lt;Contact&gt;', false)->assertDontSee('<Contact>', false)->assertSeeText('safe@example.com')->assertSeeText('+31 20 123 4567')->assertSeeText('Inactief');
    }

    public function test_view_only_is_read_only_and_update_permission_is_required_for_every_mutation(): void
    {
        $this->authorizedScenario();
        $relationId = $this->relation(self::ADMIN_A, 32, 'CONTACT-32', 'Read only', true);
        $contactId = $this->contact($relationId, 32, 'Read Person', null, null);

        $this->get(route('relations.show', $relationId->toString()))->assertOk()->assertDontSeeText('Contactpersoon toevoegen')->assertDontSeeText('Bewerken');
        $this->get(route('relations.contacts.show', [$relationId->toString(), $contactId->toString()]))->assertOk()->assertDontSeeText('Contactpersoon bewerken')->assertDontSeeText('Contactpersoon deactiveren');
        $this->get(route('relations.contacts.create', $relationId->toString()))->assertForbidden();
        $this->post(route('relations.contacts.store', $relationId->toString()), ['name' => 'Denied Person'])->assertForbidden();
        $this->put(route('relations.contacts.update', [$relationId->toString(), $contactId->toString()]), ['name' => 'Denied Person'])->assertForbidden();
        $this->delete(route('relations.contacts.deactivate', [$relationId->toString(), $contactId->toString()]))->assertForbidden();
        $this->post(route('relations.contacts.activate', [$relationId->toString(), $contactId->toString()]))->assertForbidden();
    }

    public function test_create_update_optional_removal_and_lifecycle_use_tenant_scoped_contracts(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Editor, self::MEMBERSHIP_A, 33);
        $this->loginWithAdministration(self::ADMIN_A);
        $relationId = $this->relation(self::ADMIN_A, 33, 'CONTACT-33', 'Mutable relation', true);

        $this->post(route('relations.contacts.store', $relationId->toString()), ['name' => 'Created Person', 'email' => '', 'phone' => '', 'administration_id' => self::ADMIN_B, 'contact_id' => $this->uuid('f', 1)->toString()])
            ->assertRedirect(route('relations.show', $relationId->toString()));
        $record = DB::table('relation_contacts')->where('relation_id', $relationId->toString())->first();
        self::assertNotNull($record);
        self::assertSame(self::ADMIN_A, $record->administration_id);
        self::assertNotSame($this->uuid('f', 1)->toString(), $record->contact_id);
        self::assertNull($record->email);
        self::assertNull($record->phone);

        $url = route('relations.contacts.update', [$relationId->toString(), $record->contact_id]);
        $this->put($url, ['name' => 'Changed Person', 'email' => 'changed@example.com', 'phone' => '+31 20 222 2222'])->assertRedirect(route('relations.show', $relationId->toString()));
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $record->contact_id, 'contact_name' => 'Changed Person', 'email' => 'changed@example.com', 'phone' => '+31 20 222 2222']);
        $this->put($url, ['name' => 'Changed Person', 'email' => '', 'phone' => ''])->assertRedirect();
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $record->contact_id, 'email' => null, 'phone' => null]);
        $this->delete(route('relations.contacts.deactivate', [$relationId->toString(), $record->contact_id]))->assertRedirect();
        $this->delete(route('relations.contacts.deactivate', [$relationId->toString(), $record->contact_id]))->assertRedirect();
        $this->post(route('relations.contacts.activate', [$relationId->toString(), $record->contact_id]))->assertRedirect();
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $record->contact_id, 'status' => 'active']);
        $this->assertDatabaseCount('relation_contacts', 1);
    }

    public function test_contact_routes_validate_and_hide_malformed_cross_relation_and_cross_tenant_ids(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Editor, self::MEMBERSHIP_A, 34);
        $this->assignRole(RelationsRole::Editor, self::MEMBERSHIP_B, 35);
        $this->loginWithAdministration(self::ADMIN_A);
        $relationA = $this->relation(self::ADMIN_A, 34, 'CONTACT-34', 'Tenant A', true);
        $otherA = $this->relation(self::ADMIN_A, 35, 'CONTACT-35', 'Other A', true);
        $relationB = $this->relation(self::ADMIN_B, 36, 'CONTACT-36', 'Tenant B', true);
        $contactA = $this->contact($relationA, 34, 'Owned Contact', null, null);

        $this->post(route('relations.contacts.store', $relationA->toString()), ['name' => 'x', 'email' => 'wrong', 'phone' => 'bad'])->assertSessionHasErrors(['name', 'email', 'phone']);
        $this->get('/relations/not-a-uuid/contacts/create')->assertNotFound();
        $this->get('/relations/'.$relationA->toString().'/contacts/not-a-uuid')->assertNotFound();
        $this->get(route('relations.contacts.show', [$otherA->toString(), $contactA->toString()]))->assertNotFound();
        $this->get(route('relations.contacts.show', [$relationB->toString(), $contactA->toString()]))->assertNotFound();
        $this->put(route('relations.contacts.update', [$otherA->toString(), $contactA->toString()]), ['name' => 'Hidden Contact'])->assertNotFound();
    }

    public function test_update_only_mutations_redirect_safely_to_app(): void
    {
        $this->provisionScenario();
        $this->assignPermissionOnly(RelationsPermission::Update, 36);
        $relationId = $this->relation(self::ADMIN_A, 37, 'CONTACT-37', 'Update only', true);
        $this->loginWithAdministration(self::ADMIN_A);

        $this->post(route('relations.contacts.store', $relationId->toString()), ['name' => 'Update Only Person'])
            ->assertRedirect(route('app'));
        $this->get(route('relations.show', $relationId->toString()))->assertForbidden();
    }

    public function test_relation_detail_renders_read_only_address_section_labels_status_and_escaped_fields(): void
    {
        $this->authorizedScenario();
        $relationId = $this->relation(self::ADMIN_A, 41, 'ADDRESS-41', 'Address relation', true);
        $this->get(route('relations.show', $relationId->toString()))->assertOk()->assertSeeText('Adressen')->assertSeeText('Nog geen adressen.')->assertDontSeeText('Adres toevoegen');
        $addressId = $this->address($relationId, 41, AddressType::Invoice, '<Unsafe line>', null, '1234 AB', 'Amsterdam', 'NL');
        $this->get(route('relations.show', $relationId->toString()))->assertOk()->assertSeeText('Factuuradres')->assertSee('&lt;Unsafe line&gt;', false)->assertDontSee('<Unsafe line>', false)->assertSeeText('Actief')->assertDontSeeText('Adres toevoegen');
        $this->get(route('relations.addresses.show', [$relationId->toString(), $addressId->toString()]))->assertOk()->assertDontSeeText('Adres bewerken')->assertDontSeeText('Adres deactiveren');
        $this->get(route('relations.addresses.create', $relationId->toString()))->assertForbidden();
    }

    public function test_address_create_update_lifecycle_and_immutable_type_use_application_contracts(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Editor, self::MEMBERSHIP_A, 42);
        $this->loginWithAdministration(self::ADMIN_A);
        $relationId = $this->relation(self::ADMIN_A, 42, 'ADDRESS-42', 'Mutable address relation', true);
        $contactId = $this->contact($relationId, 42, 'Preserved Contact', null, null);

        foreach (['visiting', 'postal', 'invoice', 'delivery'] as $index => $type) {
            $this->post(route('relations.addresses.store', $relationId->toString()), ['type' => $type, 'address_line_1' => 'Line '.($index + 1), 'address_line_2' => '', 'postal_code' => '100'.$index, 'city' => 'City', 'country_code' => 'nl', 'administration_id' => self::ADMIN_B, 'address_id' => $this->uuid('f', $index + 1)->toString()])->assertRedirect(route('relations.show', $relationId->toString()));
        }
        $this->assertDatabaseCount('relation_addresses', 4);
        $record = DB::table('relation_addresses')->where('address_type', 'visiting')->first();
        self::assertNotNull($record);
        self::assertSame(self::ADMIN_A, $record->administration_id);
        self::assertNull($record->address_line_2);

        $url = route('relations.addresses.update', [$relationId->toString(), $record->address_id]);
        $this->put($url, ['type' => 'delivery', 'address_line_1' => 'Changed line', 'address_line_2' => 'Unit 2', 'postal_code' => '2000 XY', 'city' => 'Changed City', 'country_code' => 'BE'])->assertRedirect(route('relations.show', $relationId->toString()));
        $this->assertDatabaseHas('relation_addresses', ['address_id' => $record->address_id, 'address_type' => 'visiting', 'address_line_2' => 'Unit 2', 'country_code' => 'BE']);
        $this->put($url, ['type' => 'postal', 'address_line_1' => 'Final line', 'address_line_2' => '', 'postal_code' => '3000', 'city' => 'Final City', 'country_code' => 'DE'])->assertRedirect();
        $this->assertDatabaseHas('relation_addresses', ['address_id' => $record->address_id, 'address_type' => 'visiting', 'address_line_2' => null]);
        $this->delete(route('relations.addresses.deactivate', [$relationId->toString(), $record->address_id]))->assertRedirect();
        $this->delete(route('relations.addresses.deactivate', [$relationId->toString(), $record->address_id]))->assertRedirect();
        $this->post(route('relations.addresses.activate', [$relationId->toString(), $record->address_id]))->assertRedirect();
        $this->assertDatabaseHas('relation_addresses', ['address_id' => $record->address_id, 'address_type' => 'visiting', 'active' => true]);
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $contactId->toString()]);
    }

    public function test_address_routes_validate_permissions_and_hide_untrusted_ownership_ids(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Editor, self::MEMBERSHIP_A, 43);
        $this->assignRole(RelationsRole::Editor, self::MEMBERSHIP_B, 44);
        $this->loginWithAdministration(self::ADMIN_A);
        $relationA = $this->relation(self::ADMIN_A, 43, 'ADDRESS-43', 'Tenant A', true);
        $otherA = $this->relation(self::ADMIN_A, 44, 'ADDRESS-44', 'Other A', true);
        $relationB = $this->relation(self::ADMIN_B, 45, 'ADDRESS-45', 'Tenant B', true);
        $addressId = $this->address($relationA, 43, AddressType::Postal, 'Owned line', null, '1000', 'City', 'NL');

        $this->post(route('relations.addresses.store', $relationA->toString()), ['type' => 'other', 'address_line_1' => 'x', 'postal_code' => '*', 'city' => 'x', 'country_code' => 'NLD'])->assertSessionHasErrors(['type', 'address_line_1', 'postal_code', 'city', 'country_code']);
        $this->get('/relations/not-a-uuid/addresses/create')->assertNotFound();
        $this->get('/relations/'.$relationA->toString().'/addresses/not-a-uuid')->assertNotFound();
        $this->get(route('relations.addresses.show', [$otherA->toString(), $addressId->toString()]))->assertNotFound();
        $this->get(route('relations.addresses.show', [$relationB->toString(), $addressId->toString()]))->assertNotFound();
        $this->put(route('relations.addresses.update', [$otherA->toString(), $addressId->toString()]), ['address_line_1' => 'Hidden', 'postal_code' => '1000', 'city' => 'City', 'country_code' => 'NL'])->assertNotFound();
    }

    public function test_update_only_address_create_redirects_to_app(): void
    {
        $this->provisionScenario();
        $this->assignPermissionOnly(RelationsPermission::Update, 46);
        $relationId = $this->relation(self::ADMIN_A, 46, 'ADDRESS-46', 'Update only address', true);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->post(route('relations.addresses.store', $relationId->toString()), ['type' => 'delivery', 'address_line_1' => 'Delivery line', 'postal_code' => '1000', 'city' => 'City', 'country_code' => 'NL'])->assertRedirect(route('app'));
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

    public function test_create_flow_requires_create_permission_and_safe_context(): void
    {
        $this->get('/relations/create')->assertRedirect('/login');
        $this->provisionScenario();
        $this->login();
        $this->get('/relations/create')->assertRedirect('/administrations/select');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
        $this->get('/relations/create')->assertForbidden();
        $this->assignPermissionOnly(RelationsPermission::Create, 1);

        $this->get('/relations/create')->assertOk()->assertSeeText('Nieuwe relatie')
            ->assertSee('name="_token"', false)->assertSee('name="code"', false)->assertSee('name="name"', false)
            ->assertDontSee('customer')->assertDontSee('supplier');
        $response = $this->post('/relations', [
            'code' => 'new-01',
            'name' => 'Created without View',
            'administration_id' => self::ADMIN_B,
            'relation_id' => $this->uuid('6', 99)->toString(),
            'customer' => true,
            'supplier' => true,
            'active' => false,
        ]);

        $response->assertRedirect('/app')->assertSessionHas('status', 'Relatie aangemaakt.');
        $this->assertDatabaseHas('relations', ['administration_id' => self::ADMIN_A, 'code' => 'NEW-01', 'display_name' => 'Created without View', 'active' => true]);
        $this->assertDatabaseMissing('relations', ['administration_id' => self::ADMIN_B, 'code' => 'NEW-01']);
        $this->assertDatabaseMissing('relations', ['id' => $this->uuid('6', 99)->toString()]);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_create_validation_and_duplicate_code_are_safe_field_errors(): void
    {
        $this->provisionScenario();
        $this->assignPermissionOnly(RelationsPermission::Create, 1);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->relation(self::ADMIN_A, 1, 'DUP-01', 'Existing Relation', true);

        $this->post('/relations', ['code' => 'DUP-01', 'name' => 'Duplicate'])
            ->assertSessionHasErrors(['code' => 'Deze relatiecode is al in gebruik.']);
        $this->post('/relations', ['code' => '<invalid>', 'name' => ' A '])
            ->assertSessionHasErrors(['code', 'name']);
        $this->from('/relations/create')->post('/relations', ['code' => '<invalid>', 'name' => '<Old Input>'])
            ->assertRedirect('/relations/create')->assertSessionHasErrors('code');
        $this->get('/relations/create')->assertSee('&lt;Old Input&gt;', false)->assertDontSee('<Old Input>', false);
        $this->assertDatabaseCount('relations', 1);
    }

    public function test_create_with_view_redirects_to_detail_and_index_action_is_permission_scoped(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 8);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->get('/relations')->assertDontSeeText('Nieuwe relatie');
        $this->assignPermissionOnly(RelationsPermission::Create, 2);
        $this->get('/relations')->assertSeeText('Nieuwe relatie')->assertSee('href="'.route('relations.create').'"', false);

        $response = $this->post('/relations', ['code' => 'VIEW-01', 'name' => '<Created Detail>']);
        $created = RelationRecord::query()->where('code', 'VIEW-01')->firstOrFail();
        $response->assertRedirect(route('relations.show', $created->getAttribute('id')));
        $this->get($response->headers->get('Location'))->assertSee('&lt;Created Detail&gt;', false)->assertDontSee('<Created Detail>', false);
    }

    public function test_update_without_view_uses_immutable_identity_code_and_safe_redirect(): void
    {
        $this->provisionScenario();
        $this->assignPermissionOnly(RelationsPermission::Update, 1);
        $relationId = $this->relation(self::ADMIN_A, 1, 'EDIT-01', 'Original Name', true);
        $this->loginWithAdministration(self::ADMIN_A);

        $this->get('/relations/'.$relationId->toString().'/edit')->assertOk()
            ->assertSeeText('Relatie bewerken')->assertSeeText('EDIT-01')->assertSeeText('Original Name')
            ->assertDontSee('name="code"', false)->assertSee('name="_method" value="PUT"', false)
            ->assertDontSee('customer')->assertDontSee('supplier');
        $response = $this->put('/relations/'.$relationId->toString(), [
            'name' => 'Changed Name',
            'status' => 'inactive',
            'code' => 'HACKED',
            'administration_id' => self::ADMIN_B,
            'relation_id' => $this->uuid('6', 99)->toString(),
            'customer' => true,
            'supplier' => true,
        ]);

        $response->assertRedirect('/app')->assertSessionHas('status', 'Relatie bijgewerkt.');
        $this->assertDatabaseHas('relations', ['id' => $relationId->toString(), 'administration_id' => self::ADMIN_A, 'code' => 'EDIT-01', 'display_name' => 'Changed Name', 'active' => false]);
        $this->assertDatabaseMissing('relations', ['code' => 'HACKED']);
        $this->assertDatabaseMissing('relations', ['id' => $this->uuid('6', 99)->toString()]);
        $this->put('/relations/'.$relationId->toString(), ['name' => 'Active Again', 'status' => 'active'])->assertRedirect('/app');
        $this->assertDatabaseHas('relations', ['id' => $relationId->toString(), 'display_name' => 'Active Again', 'active' => true]);
    }

    public function test_update_validation_not_found_and_tenant_isolation_are_safe(): void
    {
        $this->provisionScenario();
        $this->assignPermissionOnly(RelationsPermission::Update, 1);
        $relationA = $this->relation(self::ADMIN_A, 1, 'A-EDIT', 'Tenant A', true);
        $relationB = $this->relation(self::ADMIN_B, 2, 'B-EDIT', 'Tenant B', true);
        $this->loginWithAdministration(self::ADMIN_A);

        $this->put('/relations/'.$relationA->toString(), ['name' => ' X ', 'status' => 'deleted'])->assertSessionHasErrors(['name', 'status']);
        foreach ([$this->uuid('6', 99)->toString(), $relationB->toString(), 'not-a-uuid'] as $id) {
            $this->get('/relations/'.$id.'/edit')->assertNotFound();
            $this->put('/relations/'.$id, ['name' => 'Forbidden Change', 'status' => 'inactive'])->assertNotFound();
        }
        $this->assertDatabaseHas('relations', ['id' => $relationB->toString(), 'administration_id' => self::ADMIN_B, 'display_name' => 'Tenant B', 'active' => true]);
        $this->assertDatabaseCount('relations', 2);
    }

    public function test_edit_action_requires_update_and_success_with_view_redirects_to_detail(): void
    {
        $this->provisionScenario();
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 8);
        $relationId = $this->relation(self::ADMIN_A, 1, 'ACTION-01', 'Action Relation', true);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->get('/relations/'.$relationId->toString())->assertDontSeeText('Bewerken');
        $this->get('/relations/'.$relationId->toString().'/edit')->assertForbidden();
        $this->assignPermissionOnly(RelationsPermission::Update, 2);
        $this->get('/relations/'.$relationId->toString())->assertSeeText('Bewerken')->assertSee('href="'.route('relations.edit', $relationId->toString()).'"', false);

        $this->put('/relations/'.$relationId->toString(), ['name' => 'Updated with View', 'status' => 'active'])
            ->assertRedirect(route('relations.show', $relationId->toString()));
    }

    public function test_customer_permission_controls_idempotent_lifecycle_with_safe_mutation_only_redirect(): void
    {
        $this->provisionScenario();
        $this->provisionNumbers(self::ADMIN_A);
        $this->assignPermissionOnly(RelationsPermission::ClassifyCustomer, 1);
        $relationId = $this->relation(self::ADMIN_A, 1, 'C-WEB', 'Customer Web', true);
        $this->loginWithAdministration(self::ADMIN_A);

        $this->post('/relations/'.$relationId->toString().'/customer', ['administration_id' => self::ADMIN_B, 'supplier' => true])
            ->assertRedirect('/app')->assertSessionHas('status', 'Klantclassificatie is geactiveerd.');
        $created = DB::table('customers')->where('relation_id', $relationId->toString())->first();
        self::assertNotNull($created);
        self::assertSame(self::ADMIN_A, $created->administration_id);
        self::assertSame('C000001', $created->customer_number);
        $this->post('/relations/'.$relationId->toString().'/customer')->assertRedirect('/app');
        $this->assertDatabaseCount('customers', 1);
        $this->delete('/relations/'.$relationId->toString().'/customer')->assertRedirect('/app')->assertSessionHas('status', 'Klantclassificatie is gedeactiveerd.');
        $this->delete('/relations/'.$relationId->toString().'/customer')->assertRedirect('/app');
        $this->assertDatabaseHas('customers', ['id' => $created->id, 'customer_number' => 'C000001', 'active' => false]);
        $this->post('/relations/'.$relationId->toString().'/customer')->assertRedirect('/app');
        $this->assertDatabaseHas('customers', ['id' => $created->id, 'customer_number' => 'C000001', 'active' => true]);
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMIN_A, 'sequence_type' => 'customer', 'next_value' => 2]);
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_supplier_permission_is_independent_and_preserves_supplier_identity(): void
    {
        $this->provisionScenario();
        $this->provisionNumbers(self::ADMIN_A);
        $this->assignPermissionOnly(RelationsPermission::ClassifySupplier, 1);
        $relationId = $this->relation(self::ADMIN_A, 1, 'S-WEB', 'Supplier Web', true);
        $this->loginWithAdministration(self::ADMIN_A);

        $this->post('/relations/'.$relationId->toString().'/supplier')->assertRedirect('/app');
        $created = DB::table('suppliers')->where('relation_id', $relationId->toString())->first();
        self::assertNotNull($created);
        self::assertSame('S000001', $created->supplier_number);
        $this->post('/relations/'.$relationId->toString().'/supplier')->assertRedirect('/app');
        $this->delete('/relations/'.$relationId->toString().'/supplier')->assertRedirect('/app');
        $this->post('/relations/'.$relationId->toString().'/supplier')->assertRedirect('/app');
        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseHas('suppliers', ['id' => $created->id, 'supplier_number' => 'S000001', 'active' => true]);
        $this->assertDatabaseCount('customers', 0);
        $this->post('/relations/'.$relationId->toString().'/customer')->assertForbidden();
    }

    public function test_classification_permissions_do_not_authorize_each_other_or_view_only_users(): void
    {
        $this->provisionScenario();
        $this->provisionNumbers(self::ADMIN_A);
        $relationId = $this->relation(self::ADMIN_A, 1, 'PERM-01', 'Permission Relation', true);
        $this->assignRole(RelationsRole::Viewer, self::MEMBERSHIP_A, 8);
        $this->loginWithAdministration(self::ADMIN_A);
        $this->get('/relations/'.$relationId->toString())->assertOk()
            ->assertDontSeeText('Als klant classificeren')->assertDontSeeText('Als leverancier classificeren');
        $this->post('/relations/'.$relationId->toString().'/customer')->assertForbidden();
        $this->post('/relations/'.$relationId->toString().'/supplier')->assertForbidden();

        $this->assignPermissionOnly(RelationsPermission::ClassifyCustomer, 1);
        $this->get('/relations/'.$relationId->toString())->assertSeeText('Als klant classificeren')->assertDontSeeText('Als leverancier classificeren')
            ->assertSee('name="_token"', false);
        $this->post('/relations/'.$relationId->toString().'/supplier')->assertForbidden();
        $this->post('/relations/'.$relationId->toString().'/customer')->assertRedirect(route('relations.show', $relationId->toString()));
        $this->get('/relations/'.$relationId->toString())->assertSeeText('Klantclassificatie verwijderen')->assertSee('name="_method" value="DELETE"', false);
    }

    public function test_classification_routes_are_tenant_safe_and_reject_malformed_or_wrong_methods(): void
    {
        $this->provisionScenario();
        $this->provisionNumbers(self::ADMIN_A);
        $this->assignPermissionOnly(RelationsPermission::ClassifyCustomer, 1);
        $relationA = $this->relation(self::ADMIN_A, 1, 'A-CLASS', 'Tenant A Classification', true);
        $relationB = $this->relation(self::ADMIN_B, 2, 'B-CLASS', 'Tenant B Classification', true);
        $this->customer(self::ADMIN_B, $relationB, 2, false);
        $this->loginWithAdministration(self::ADMIN_A);

        foreach ([$relationB->toString(), 'not-a-uuid'] as $id) {
            $this->post('/relations/'.$id.'/customer', ['administration_id' => self::ADMIN_B])->assertNotFound();
            $this->delete('/relations/'.$id.'/customer')->assertNotFound();
        }
        $this->get('/relations/'.$relationA->toString().'/customer')->assertStatus(405);
        $this->assertDatabaseHas('customers', ['administration_id' => self::ADMIN_B, 'relation_id' => $relationB->toString(), 'active' => false]);
        $this->assertDatabaseCount('customers', 1);
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

    private function provisionNumbers(string $administrationId): void
    {
        $this->app->make(RelationNumberSequenceProvisioner::class)->ensureForAdministration(new AdministrationId(new Uuid($administrationId)));
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

    private function contact(RelationId $relationId, int $sequence, string $name, ?string $email, ?string $phone): ContactId
    {
        $contactId = new ContactId($this->uuid('3', $sequence));
        $result = $this->app->make(CreateContact::class)->execute(
            new AdministrationId(new Uuid(self::ADMIN_A)), $relationId, $contactId, new ContactName($name),
            $email === null ? null : new ContactEmailAddress($email), $phone === null ? null : new PhoneNumber($phone),
        );
        self::assertSame(ContactWriteResult::Success, $result);

        return $contactId;
    }

    private function address(RelationId $relationId, int $sequence, AddressType $type, string $line1, ?string $line2, string $postal, string $city, string $country): AddressId
    {
        $id = new AddressId($this->uuid('2', $sequence));
        self::assertSame(AddressWriteResult::Success, $this->app->make(CreateAddress::class)->execute(new AdministrationId(new Uuid(self::ADMIN_A)), $relationId, $id, $type, new AddressLine($line1), $line2 === null ? null : new AddressLine($line2), new PostalCode($postal), new City($city), new CountryCode($country)));

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
