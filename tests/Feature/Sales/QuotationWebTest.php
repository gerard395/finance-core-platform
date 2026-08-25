<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Identity\ProvisionUserAccount;
use App\Application\Sales\AcceptQuotation;
use App\Application\Sales\AddQuotationLine;
use App\Application\Sales\CreateQuotation;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\SendQuotation;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\Definitions\SalesRole;
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
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Http\Middleware\EnsureSalesPermission;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OrderRecord;
use App\Infrastructure\Persistence\Eloquent\Models\QuotationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class QuotationWebTest extends TestCase
{
    use RefreshDatabase;

    private const USER = 'a1000000-0000-4000-8000-000000000001';

    private const ADMIN_A = 'a1000000-0000-4000-8000-000000000002';

    private const ADMIN_B = 'b1000000-0000-4000-8000-000000000002';

    private const MEMBERSHIP_A = 'a1000000-0000-4000-8000-000000000003';

    private const MEMBERSHIP_B = 'b1000000-0000-4000-8000-000000000003';

    private const CUSTOMER_A = 'a1000000-0000-4000-8000-000000000004';

    private const CUSTOMER_B = 'b1000000-0000-4000-8000-000000000004';

    protected function setUp(): void
    {
        parent::setUp();
        $this->provision();
    }

    public function test_authorization_navigation_and_empty_states_follow_exact_permissions(): void
    {
        $this->get('/sales/quotations')->assertRedirect('/login');
        $this->login();
        $this->get('/sales/quotations')->assertForbidden();

        $this->assign(SalesRole::Viewer, 1);
        $this->get('/sales/quotations')->assertOk()->assertSee('Nog geen offertes.')->assertSee('Offertes')->assertDontSee('Nieuwe offerte');
        $this->get('/sales/quotations/create')->assertForbidden();
    }

    public function test_create_uses_active_tenant_customer_base_currency_and_server_owned_fields(): void
    {
        $this->assign(SalesRole::Editor, 2);
        $this->login();
        $this->get('/sales/quotations/create')->assertOk()->assertSee('C-A')->assertDontSee('C-B')->assertDontSee('C-INACTIVE');
        $response = $this->post('/sales/quotations', [
            'customer_id' => self::CUSTOMER_A,
            'quotation_address_id' => $this->addressId(self::CUSTOMER_A),
            'quotation_date' => '2026-08-21',
            'expiry_date' => '2026-09-21',
            'administration_id' => self::ADMIN_B,
            'quotation_number' => 'HACKED',
            'currency' => 'USD',
            'status' => 'accepted',
        ]);
        $quotation = QuotationRecord::query()->firstOrFail();
        $response->assertRedirect('/sales/quotations/'.$quotation->getAttribute('id'));
        self::assertSame('Q000001', $quotation->getAttribute('quotation_number'));
        self::assertSame(self::ADMIN_A, $quotation->getAttribute('administration_id'));
        self::assertSame('EUR', $quotation->getAttribute('currency'));
        self::assertSame('draft', $quotation->getAttribute('status'));
        self::assertSame('Customer <script>alert(1)</script>', $quotation->getAttribute('customer_name_snapshot'));
        self::assertSame('quotation', $quotation->getAttribute('quotation_address_type_snapshot'));
        self::assertSame('Quotation street A', $quotation->getAttribute('quotation_address_line_1_snapshot'));
        $this->get('/sales/quotations/'.$quotation->getAttribute('id'))->assertOk()->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Quotation street A')->assertDontSee('<script>alert(1)</script>', false);

        $this->post('/sales/quotations', ['customer_id' => self::CUSTOMER_B, 'quotation_address_id' => $this->addressId(self::CUSTOMER_B), 'quotation_date' => '2026-08-21'])->assertSessionHasErrors('customer_id');
        RelationAddressRecord::query()->whereKey($this->addressId(self::CUSTOMER_A))->update(['active' => false, 'address_line_1' => 'Changed street 99']);
        $this->get('/sales/quotations/create')->assertOk()->assertSee('Er is nog geen actief offerteadres vastgelegd.');
        $this->post('/sales/quotations', ['customer_id' => self::CUSTOMER_A, 'quotation_address_id' => $this->addressId(self::CUSTOMER_A), 'quotation_date' => '2026-08-21'])->assertSessionHasErrors('quotation_address_id');
        $this->get('/sales/quotations/'.$quotation->getAttribute('id'))->assertOk()->assertSee('Quotation street A')->assertDontSee('Changed street 99');
        RelationAddressRecord::query()->whereKey($this->addressId(self::CUSTOMER_A))->update(['active' => true]);
        CustomerRecord::query()->whereKey(self::CUSTOMER_A)->update(['active' => false]);
        $this->post('/sales/quotations', ['customer_id' => self::CUSTOMER_A, 'quotation_address_id' => $this->addressId(self::CUSTOMER_A), 'quotation_date' => '2026-08-21'])->assertSessionHasErrors('customer_id');
    }

    public function test_index_detail_filtering_and_tenant_isolation_use_persisted_snapshots(): void
    {
        $this->assign(SalesRole::Editor, 3);
        $this->login();
        $a = $this->quotation(self::ADMIN_A, self::CUSTOMER_A, 1);
        $b = $this->quotation(self::ADMIN_B, self::CUSTOMER_B, 2);
        RelationRecord::query()->where('administration_id', self::ADMIN_A)->update(['display_name' => 'Live renamed customer']);

        $this->get('/sales/quotations')->assertOk()->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('Customer B')->assertSee('Q000001');
        $this->get('/sales/quotations?q=absent')->assertOk()->assertSee('Geen offertes gevonden met deze filters.');
        $this->get('/sales/quotations?status=draft&sort=number&direction=asc&per_page=25')->assertOk()->assertSee('Q000001');
        $this->get('/sales/quotations/'.$a->toString())->assertOk()->assertSee('Q000001')->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('Live renamed customer');
        $this->get('/sales/quotations/'.$b->toString())->assertNotFound();
        $this->put('/sales/quotations/'.$b->toString(), ['quotation_date' => '2026-08-22'])->assertNotFound();
        $this->get('/sales/quotations/not-a-uuid')->assertNotFound();
    }

    public function test_draft_lines_preserve_exact_decimals_identity_currency_and_cross_document_scope(): void
    {
        $this->assign(SalesRole::Editor, 4);
        $this->login();
        $first = $this->quotation(self::ADMIN_A, self::CUSTOMER_A, 1);
        $second = $this->quotation(self::ADMIN_A, self::CUSTOMER_A, 2);
        $this->post('/sales/quotations/'.$first->toString().'/lines', ['description' => '<b>Consultancy</b>', 'quantity' => '1.25', 'unit_price' => '1234.5678'])->assertRedirect();
        $line = DB::table('quotation_lines')->where('quotation_id', $first->toString())->first();
        self::assertNotNull($line);
        self::assertSame('1.25', $line->quantity);
        self::assertSame('1234.5678', $line->unit_price_amount);
        self::assertSame('EUR', $line->currency);
        $this->put('/sales/quotations/'.$first->toString().'/lines/'.$line->id, ['description' => 'Updated line', 'quantity' => '0.1', 'unit_price' => '1.25', 'line_id' => 'b1000000-0000-4000-8000-000000000099', 'currency' => 'USD'])->assertRedirect();
        $this->assertDatabaseHas('quotation_lines', ['id' => $line->id, 'quantity' => '0.1', 'unit_price_amount' => '1.25', 'currency' => 'EUR']);
        $this->put('/sales/quotations/'.$second->toString().'/lines/'.$line->id, ['description' => 'Attack', 'quantity' => '1', 'unit_price' => '1'])->assertNotFound();
        $this->delete('/sales/quotations/'.$first->toString().'/lines/'.$line->id)->assertRedirect();
        $this->assertDatabaseMissing('quotation_lines', ['id' => $line->id]);
        $this->post('/sales/quotations/'.$first->toString().'/lines', ['description' => 'x', 'quantity' => '0', 'unit_price' => '-1'])->assertSessionHasErrors(['description', 'quantity', 'unit_price']);
    }

    public function test_lifecycle_and_non_draft_edits_fail_safely_and_mutation_only_redirects_to_app(): void
    {
        $this->assign(SalesRole::Editor, 5);
        $this->login();
        $id = $this->quotation(self::ADMIN_A, self::CUSTOMER_A, 1);
        $this->app->make(AddQuotationLine::class)->execute($this->admin(self::ADMIN_A), $id, $this->line(1));
        $this->post('/sales/quotations/'.$id->toString().'/send')->assertNotFound();
        $this->app->make(SendQuotation::class)->execute($this->admin(self::ADMIN_A), $id);
        $this->assertDatabaseMissing('orders', ['source_quotation_id' => $id->toString()]);
        $this->get('/sales/quotations/'.$id->toString())->assertOk()->assertDontSee('Order maken');
        $this->get('/sales/quotations/'.$id->toString().'/edit')->assertStatus(409);
        $this->put('/sales/quotations/'.$id->toString(), ['quotation_date' => '2026-08-22'])->assertRedirect()->assertSessionHas('error');
        $this->post('/sales/quotations/'.$id->toString().'/accept')->assertRedirect();
        $this->get('/sales/quotations/'.$id->toString())->assertOk()->assertSee('Order maken');
        $this->post('/sales/quotations/'.$id->toString().'/reject')->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('quotations', ['id' => $id->toString(), 'status' => 'accepted']);

        $rejected = $this->quotation(self::ADMIN_A, self::CUSTOMER_A, 2);
        $this->app->make(AddQuotationLine::class)->execute($this->admin(self::ADMIN_A), $rejected, $this->line(2));
        $this->post('/sales/quotations/'.$rejected->toString().'/send')->assertNotFound();
        $this->app->make(SendQuotation::class)->execute($this->admin(self::ADMIN_A), $rejected);
        $this->post('/sales/quotations/'.$rejected->toString().'/reject')->assertRedirect();
        $this->assertDatabaseHas('quotations', ['id' => $rejected->toString(), 'status' => 'rejected']);

        $expired = $this->quotation(self::ADMIN_A, self::CUSTOMER_A, 3);
        $this->post('/sales/quotations/'.$expired->toString().'/expire')->assertRedirect();
        $this->assertDatabaseHas('quotations', ['id' => $expired->toString(), 'status' => 'expired']);
    }

    public function test_manage_permission_does_not_imply_view_and_redirects_after_mutation(): void
    {
        $roleId = new RoleId(new Uuid('f1000000-0000-4000-8000-000000000001'));
        (new EloquentRoleRepository)->save(new Role($roleId, new RoleCode('QUOTATION_ONLY'), new RoleName('Quotation only'), null, RoleStatus::Active));
        (new EloquentRolePermissionRepository)->save(new RolePermission(new RolePermissionId(new Uuid('f1000000-0000-4000-8000-000000000002')), $roleId, SalesPermission::ManageQuotations->id(), true));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(new MembershipRoleId(new Uuid('f1000000-0000-4000-8000-000000000003')), new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $roleId, true));
        $this->login();

        $this->get('/sales/quotations')->assertForbidden();
        $this->get('/sales/quotations/create')->assertOk()->assertDontSee('href="'.route('sales.quotations.index').'"', false);
        $this->post('/sales/quotations', ['customer_id' => self::CUSTOMER_A, 'quotation_address_id' => $this->addressId(self::CUSTOMER_A), 'quotation_date' => '2026-08-21'])->assertRedirect('/app');
    }

    public function test_accepted_quotation_exposes_order_action_and_conversion_preserves_source_data(): void
    {
        $this->assign(SalesRole::Editor, 6);
        $this->login();
        $quotation = $this->acceptedQuotation(self::ADMIN_A, self::CUSTOMER_A, 10);

        $this->get('/sales/quotations/'.$quotation->toString())
            ->assertOk()
            ->assertSee('Order maken')
            ->assertSee('name="order_date"', false)
            ->assertSee(route('sales.quotations.order.store', $quotation->toString()), false);

        $response = $this->post('/sales/quotations/'.$quotation->toString().'/order', [
            'order_date' => '2026-08-24',
            'administration_id' => self::ADMIN_B,
            'customer_id' => self::CUSTOMER_B,
            'status' => 'confirmed',
        ]);
        $order = OrderRecord::query()->where('source_quotation_id', $quotation->toString())->firstOrFail();
        $response->assertRedirect('/sales/orders/'.$order->getAttribute('id'))->assertSessionHas('status', 'Order aangemaakt.');
        $this->assertDatabaseHas('orders', [
            'id' => $order->getAttribute('id'),
            'administration_id' => self::ADMIN_A,
            'source_quotation_id' => $quotation->toString(),
            'customer_id' => self::CUSTOMER_A,
            'order_date' => '2026-08-24',
            'status' => 'draft',
        ]);
        self::assertSame(1, DB::table('order_lines')->where('order_id', $order->getAttribute('id'))->count());
        $this->assertDatabaseHas('quotations', ['id' => $quotation->toString(), 'status' => 'accepted']);

        $this->get('/sales/quotations/'.$quotation->toString())->assertOk()->assertSee('Bekijk order')->assertDontSee('Order maken');
        $this->post('/sales/quotations/'.$quotation->toString().'/order', ['order_date' => '2026-08-25'])
            ->assertRedirect('/sales/orders/'.$order->getAttribute('id'))
            ->assertSessionHas('status', 'Voor deze offerte bestaat al een order.');
        self::assertSame(1, OrderRecord::query()->where('source_quotation_id', $quotation->toString())->count());
    }

    public function test_order_conversion_enforces_exact_permission_tenant_and_runtime_revocation(): void
    {
        $quotationA = $this->acceptedQuotation(self::ADMIN_A, self::CUSTOMER_A, 11);
        $quotationB = $this->acceptedQuotation(self::ADMIN_B, self::CUSTOMER_B, 12);
        $this->login();
        $this->assign(SalesRole::Viewer, 7);

        $this->get('/sales/quotations/'.$quotationA->toString())->assertOk()->assertDontSee('Order maken');
        $this->post('/sales/quotations/'.$quotationA->toString().'/order', ['order_date' => '2026-08-24'])->assertForbidden();

        $this->assign(SalesRole::Editor, 8);
        $this->post('/sales/quotations/'.$quotationB->toString().'/order', ['order_date' => '2026-08-24'])->assertNotFound();
        $this->post('/sales/quotations/not-a-uuid/order', ['order_date' => '2026-08-24'])->assertNotFound();
        DB::table('administration_membership_roles')->where('membership_id', self::MEMBERSHIP_A)->delete();
        $this->post('/sales/quotations/'.$quotationA->toString().'/order', ['order_date' => '2026-08-24'])->assertForbidden();
        $this->assertDatabaseMissing('orders', ['source_quotation_id' => $quotationA->toString()]);
    }

    public function test_mutation_only_redirect_validation_and_failure_messages_are_safe(): void
    {
        $this->grantOnly(SalesPermission::ManageOrders, 20);
        $this->login();
        $accepted = $this->acceptedQuotation(self::ADMIN_A, self::CUSTOMER_A, 13);

        $this->post('/sales/quotations/'.$accepted->toString().'/order', [])->assertSessionHasErrors('order_date');
        $this->post('/sales/quotations/'.$accepted->toString().'/order', ['order_date' => '24-08-2026'])->assertSessionHasErrors('order_date');
        $this->post('/sales/quotations/'.$accepted->toString().'/order', ['order_date' => '2026-08-24'])
            ->assertRedirect('/app')->assertSessionHas('status', 'Order aangemaakt.');

        $draft = $this->quotation(self::ADMIN_A, self::CUSTOMER_A, 14);
        $this->post('/sales/quotations/'.$draft->toString().'/order', ['order_date' => '2026-08-24'])
            ->assertRedirect('/app')->assertSessionHas('error', 'Deze offerte kan niet naar een order worden omgezet.');
        $missing = $this->acceptedQuotation(self::ADMIN_A, self::CUSTOMER_A, 15);
        DB::table('sales_number_sequences')->where('administration_id', self::ADMIN_A)->where('sequence_type', 'order')->delete();
        $this->post('/sales/quotations/'.$missing->toString().'/order', ['order_date' => '2026-08-24'])
            ->assertRedirect('/app')->assertSessionHas('error', 'De ordernummerreeks is niet beschikbaar.');
    }

    public function test_quotation_conversion_and_order_invoicing_routes_remain_separate_and_permission_scoped(): void
    {
        self::assertTrue(Route::has('sales.quotations.order.store'));
        $route = Route::getRoutes()->getByName('sales.quotations.order.store');
        self::assertNotNull($route);
        self::assertSame(['POST'], $route->methods());
        self::assertContains(EnsureSalesPermission::using(SalesPermission::ManageOrders), $route->gatherMiddleware());
        $create = Route::getRoutes()->getByName('sales.orders.invoice.create');
        $store = Route::getRoutes()->getByName('sales.orders.invoice.store');
        self::assertNotNull($create);
        self::assertNotNull($store);
        self::assertSame(['GET', 'HEAD'], $create->methods());
        self::assertSame(['POST'], $store->methods());
        self::assertContains(EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts), $create->gatherMiddleware());
        self::assertContains(EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts), $store->gatherMiddleware());
    }

    private function provision(): void
    {
        $user = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($user, new UserDisplayName('Sales Web User'), new EmailAddress('sales-web@example.com'), 'correct-secure-password');
        $administrations = new EloquentAdministrationRepository;
        $memberships = new EloquentAdministrationMembershipRepository;
        foreach ([[self::ADMIN_A, self::MEMBERSHIP_A, 'SWA'], [self::ADMIN_B, self::MEMBERSHIP_B, 'SWB']] as [$admin, $membership, $code]) {
            $administrations->save(new Administration($this->admin($admin), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active));
            $memberships->save(new AdministrationMembership(new AdministrationMembershipId(new Uuid($membership)), $user, $this->admin($admin), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
            $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin($admin));
        }
        $this->app->make(SalesAuthorizationProvisioner::class)->provision();
        $this->customer(self::ADMIN_A, self::CUSTOMER_A, 'A', 'Customer <script>alert(1)</script>', true);
        $this->customer(self::ADMIN_A, 'a1000000-0000-4000-8000-000000000005', 'INACTIVE', 'Inactive customer', false);
        $this->customer(self::ADMIN_B, self::CUSTOMER_B, 'B', 'Customer B', true);
    }

    private function customer(string $admin, string $customer, string $code, string $name, bool $active): void
    {
        $relation = str_replace('000000000004', '000000000014', $customer);
        RelationRecord::query()->create(['id' => $relation, 'administration_id' => $admin, 'code' => 'R-'.$code, 'display_name' => $name, 'active' => true]);
        CustomerRecord::query()->create(['id' => $customer, 'administration_id' => $admin, 'relation_id' => $relation, 'customer_number' => 'C-'.$code, 'active' => $active]);
        RelationAddressRecord::query()->create(['address_id' => $this->addressId($customer), 'administration_id' => $admin, 'relation_id' => $relation, 'address_type' => 'quotation', 'address_line_1' => 'Quotation street '.$code, 'address_line_2' => null, 'postal_code' => '1234AB', 'city' => 'Amsterdam', 'country_code' => 'NL', 'active' => true]);
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'sales-web@example.com', 'password' => 'correct-secure-password'])->assertRedirect();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
    }

    private function assign(SalesRole $role, int $sequence): void
    {
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(new MembershipRoleId(new Uuid(sprintf('c1000000-0000-4000-8000-%012d', $sequence))), new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $role->id(), true));
    }

    private function quotation(string $admin, string $customer, int $sequence): QuotationId
    {
        $id = new QuotationId(new Uuid(sprintf('d1000000-0000-4000-8000-%012d', $sequence)));
        self::assertSame('Success', $this->app->make(CreateQuotation::class)->execute($this->admin($admin), $id, new CustomerId(new Uuid($customer)), new AddressId(new Uuid($this->addressId($customer))), new Currency('EUR'), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-21'))->name);

        return $id;
    }

    private function addressId(string $customer): string
    {
        $hash = md5('quotation-address-'.$customer);
        $hash[12] = '4';
        $hash[16] = '8';

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    private function line(int $sequence): QuotationLine
    {
        return new QuotationLine(new QuotationLineId(new Uuid(sprintf('e1000000-0000-4000-8000-%012d', $sequence))), new LineDescription('Service line'), new Quantity('2'), new Money('10', new Currency('EUR')));
    }

    private function acceptedQuotation(string $admin, string $customer, int $sequence): QuotationId
    {
        $id = $this->quotation($admin, $customer, $sequence);
        $this->app->make(AddQuotationLine::class)->execute($this->admin($admin), $id, $this->line($sequence));
        $this->app->make(SendQuotation::class)->execute($this->admin($admin), $id);
        $this->app->make(AcceptQuotation::class)->execute($this->admin($admin), $id);

        return $id;
    }

    private function grantOnly(SalesPermission $permission, int $sequence): void
    {
        $roleId = new RoleId(new Uuid(sprintf('f2000000-0000-4000-8000-%012d', $sequence)));
        (new EloquentRoleRepository)->save(new Role($roleId, new RoleCode('CUSTOM_'.$sequence), new RoleName('Custom '.$sequence), null, RoleStatus::Active));
        (new EloquentRolePermissionRepository)->save(new RolePermission(new RolePermissionId(new Uuid(sprintf('f3000000-0000-4000-8000-%012d', $sequence))), $roleId, $permission->id(), true));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(new MembershipRoleId(new Uuid(sprintf('f4000000-0000-4000-8000-%012d', $sequence))), new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $roleId, true));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}
