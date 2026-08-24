<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Identity\ProvisionUserAccount;
use App\Application\Sales\CreateSalesInvoice;
use App\Application\Sales\SalesNumberSequenceProvisioner;
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
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxCodeRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SalesInvoiceWebTest extends TestCase
{
    use RefreshDatabase;

    private const USER = '81000000-0000-4000-8000-000000000001';

    private const ADMIN_A = '81000000-0000-4000-8000-000000000002';

    private const ADMIN_B = '82000000-0000-4000-8000-000000000002';

    private const MEMBERSHIP_A = '81000000-0000-4000-8000-000000000003';

    private const MEMBERSHIP_B = '82000000-0000-4000-8000-000000000003';

    private const CUSTOMER_A = '81000000-0000-4000-8000-000000000004';

    private const CUSTOMER_B = '82000000-0000-4000-8000-000000000004';

    private const ADDRESS_A = '81000000-0000-4000-8000-000000000005';

    private const ADDRESS_B = '82000000-0000-4000-8000-000000000005';

    private const TAX_ACTIVE = '81000000-0000-4000-8000-000000000006';

    private const TAX_INACTIVE = '81000000-0000-4000-8000-000000000007';

    private const TAX_INPUT = '81000000-0000-4000-8000-000000000008';

    private const TAX_B = '82000000-0000-4000-8000-000000000006';

    protected function setUp(): void
    {
        parent::setUp();
        $this->provision();
    }

    public function test_authorization_navigation_empty_states_and_no_post_or_paid_routes(): void
    {
        $this->get('/sales/invoices')->assertRedirect('/login');
        $this->login();
        $this->get('/sales/invoices')->assertForbidden();
        $this->assign(SalesRole::Viewer, 1);
        $this->get('/sales/invoices')->assertOk()->assertSee('Nog geen verkoopfacturen.')->assertSee('Facturen')->assertDontSee('Nieuwe factuur');
        $this->get('/sales/invoices/create')->assertForbidden();
        self::assertFalse(Route::has('sales.invoices.post'));
        self::assertFalse(Route::has('sales.invoices.paid'));
        $this->post('/sales/invoices/not-a-uuid/post')->assertNotFound();
    }

    public function test_direct_create_uses_tenant_customer_unique_invoice_address_and_server_owned_fields(): void
    {
        $this->assign(SalesRole::Editor, 2);
        $this->login();
        $this->get('/sales/invoices/create')->assertOk()->assertSee('C-A')->assertDontSee('C-B')->assertDontSee('sourceOrderId')->assertDontSee('Bronorder');
        $response = $this->post('/sales/invoices', [
            'customer_id' => self::CUSTOMER_A, 'invoice_date' => '2026-08-24', 'due_date' => '2026-09-24',
            'administration_id' => self::ADMIN_B, 'sales_invoice_number' => 'HACKED', 'currency' => 'USD', 'status' => 'paid', 'source_order_id' => '82000000-0000-4000-8000-000000000099',
        ]);
        $invoice = SalesInvoiceRecord::query()->firstOrFail();
        $response->assertRedirect('/sales/invoices/'.$invoice->getAttribute('id'));
        self::assertSame('F000001', $invoice->getAttribute('sales_invoice_number'));
        self::assertSame(self::ADMIN_A, $invoice->getAttribute('administration_id'));
        self::assertSame('EUR', $invoice->getAttribute('currency'));
        self::assertSame('draft', $invoice->getAttribute('status'));
        self::assertNull($invoice->getAttribute('source_order_id'));
        self::assertSame('Customer <script>alert(1)</script>', $invoice->getAttribute('customer_name_snapshot'));
        self::assertSame('Invoice <script>alert(2)</script>', $invoice->getAttribute('invoice_address_line_1_snapshot'));
        $this->get('/sales/invoices/'.$invoice->getAttribute('id'))->assertOk()->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

        $this->post('/sales/invoices', ['customer_id' => self::CUSTOMER_B, 'invoice_date' => '2026-08-24', 'due_date' => '2026-09-24'])->assertSessionHasErrors('customer_id');
        RelationAddressRecord::query()->whereKey(self::ADDRESS_A)->update(['active' => false]);
        $this->post('/sales/invoices', ['customer_id' => self::CUSTOMER_A, 'invoice_date' => '2026-08-24', 'due_date' => '2026-09-24'])->assertSessionHasErrors('customer_id');
    }

    public function test_index_detail_filters_totals_and_tenant_isolation_use_snapshots(): void
    {
        $this->assign(SalesRole::Editor, 3);
        $this->login();
        $a = $this->invoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 1);
        $b = $this->invoice(self::ADMIN_B, self::CUSTOMER_B, self::ADDRESS_B, 2);
        RelationRecord::query()->where('administration_id', self::ADMIN_A)->update(['display_name' => 'Live renamed customer']);

        $this->get('/sales/invoices')->assertOk()->assertSee('F000001')->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('Customer B')->assertSee('EUR 0,00');
        $this->get('/sales/invoices?q=absent')->assertOk()->assertSee('Geen verkoopfacturen gevonden met deze filters.');
        $this->get('/sales/invoices?status=draft&customer='.self::CUSTOMER_A.'&date_from=2026-08-01&date_to=2026-08-31&sort=number&direction=asc&per_page=25')->assertOk()->assertSee('F000001');
        $this->get('/sales/invoices/'.$a->toString())->assertOk()->assertSee('Invoice &lt;script&gt;alert(2)&lt;/script&gt;', false)->assertDontSee('Live renamed customer');
        $this->get('/sales/invoices/'.$b->toString())->assertNotFound();
        $this->put('/sales/invoices/'.$b->toString(), ['invoice_date' => '2026-08-25', 'due_date' => '2026-09-25'])->assertNotFound();
        $this->get('/sales/invoices/not-a-uuid')->assertNotFound();
    }

    public function test_tax_catalogue_only_shows_active_tenant_output_codes_and_missing_catalogue_is_explicit(): void
    {
        $this->assign(SalesRole::Editor, 4);
        $this->login();
        $id = $this->invoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 1);
        $this->get('/sales/invoices/'.$id->toString())->assertOk()->assertSee('VAT21')->assertSee('Hoge btw')->assertDontSee('VAT09')->assertDontSee('INPUT')->assertDontSee('VATB')->assertDontSee('name="tax_rate"', false);
        TaxCodeRecord::query()->where('administration_id', self::ADMIN_A)->where('direction', 'output')->where('status', 'active')->delete();
        $this->get('/sales/invoices/'.$id->toString())->assertOk()->assertSee('Geen btw-codes beschikbaar.')->assertDontSee('Regel toevoegen');
    }

    public function test_line_add_update_remove_exactness_tax_snapshot_and_cross_document_scope(): void
    {
        $this->assign(SalesRole::Editor, 5);
        $this->login();
        $first = $this->invoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 1);
        $second = $this->invoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 2);
        $this->post('/sales/invoices/'.$first->toString().'/lines', ['description' => '<b>Consultancy</b>', 'quantity' => '1.25', 'unit_price' => '10.123456', 'tax_code_id' => self::TAX_ACTIVE, 'tax_rate' => '99', 'currency' => 'USD'])->assertRedirect();
        $line = DB::table('sales_invoice_lines')->where('sales_invoice_id', $first->toString())->first();
        self::assertNotNull($line);
        self::assertSame('1.25', $line->quantity);
        self::assertSame('10.123456', $line->unit_price_amount);
        self::assertSame('EUR', $line->currency);
        self::assertSame('21', $line->tax_rate_snapshot);
        $this->get('/sales/invoices/'.$first->toString())->assertOk()->assertSee('&lt;b&gt;Consultancy&lt;/b&gt;', false)->assertSee('EUR 12,65432')->assertSee('EUR 2,6574072')->assertSee('EUR 15,3117272');

        TaxCodeRecord::query()->whereKey(self::TAX_ACTIVE)->update(['rate' => '9', 'name' => 'Nieuwe btw']);
        $this->put('/sales/invoices/'.$first->toString().'/lines/'.$line->id, ['description' => 'Updated line', 'quantity' => '2', 'unit_price' => '5', 'tax_code_id' => self::TAX_ACTIVE, 'line_id' => '82000000-0000-4000-8000-000000000099', 'tax_rate' => '100'])->assertRedirect();
        $this->assertDatabaseHas('sales_invoice_lines', ['id' => $line->id, 'quantity' => '2', 'unit_price_amount' => '5', 'tax_rate_snapshot' => '9']);
        $this->put('/sales/invoices/'.$second->toString().'/lines/'.$line->id, ['description' => 'Attack', 'quantity' => '1', 'unit_price' => '1', 'tax_code_id' => self::TAX_ACTIVE])->assertNotFound();
        $this->post('/sales/invoices/'.$first->toString().'/lines', ['description' => 'Cross tax', 'quantity' => '1', 'unit_price' => '1', 'tax_code_id' => self::TAX_B])->assertSessionHasErrors('tax_code_id');
        $this->post('/sales/invoices/'.$first->toString().'/lines', ['description' => 'Impossible', 'quantity' => '0.00000001', 'unit_price' => '0.00000001', 'tax_code_id' => self::TAX_ACTIVE])->assertSessionHasErrors('tax_code_id');
        $this->delete('/sales/invoices/'.$first->toString().'/lines/'.$line->id)->assertRedirect();
        $this->assertDatabaseMissing('sales_invoice_lines', ['id' => $line->id]);
    }

    public function test_edit_finalize_cancel_and_read_only_posted_paid_boundaries(): void
    {
        $this->assign(SalesRole::Manager, 6);
        $this->login();
        $id = $this->invoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 1);
        $this->put('/sales/invoices/'.$id->toString(), ['invoice_date' => '2026-08-25', 'due_date' => '2026-08-24', 'customer_id' => self::CUSTOMER_B, 'status' => 'paid'])->assertSessionHasErrors('due_date');
        $this->post('/sales/invoices/'.$id->toString().'/finalize')->assertRedirect()->assertSessionHas('error');
        $this->post('/sales/invoices/'.$id->toString().'/lines', ['description' => 'Service', 'quantity' => '2', 'unit_price' => '10', 'tax_code_id' => self::TAX_ACTIVE])->assertRedirect();
        $this->post('/sales/invoices/'.$id->toString().'/finalize')->assertRedirect('/sales/invoices/'.$id->toString());
        $this->assertDatabaseHas('sales_invoices', ['id' => $id->toString(), 'status' => 'finalized', 'customer_id' => self::CUSTOMER_A]);
        $this->get('/sales/invoices/'.$id->toString())->assertOk()->assertDontSee('Header bewerken')->assertDontSee('Regel toevoegen')->assertSee('Factuur annuleren')->assertDontSee('Factuur boeken')->assertDontSee('Betaald markeren');
        $this->post('/sales/invoices/'.$id->toString().'/cancel')->assertRedirect();
        $this->assertDatabaseHas('sales_invoices', ['id' => $id->toString(), 'status' => 'cancelled']);
        $this->post('/sales/invoices/'.$id->toString().'/cancel')->assertRedirect()->assertSessionDoesntHaveErrors();

        SalesInvoiceRecord::query()->whereKey($id->toString())->update(['status' => 'posted']);
        $this->get('/sales/invoices/'.$id->toString())->assertOk()->assertSee('Geboekt')->assertDontSee('Header bewerken')->assertDontSee('Factuur annuleren')->assertDontSee('Factuur boeken')->assertDontSee('Betaald markeren');
        $this->post('/sales/invoices/'.$id->toString().'/cancel')->assertRedirect()->assertSessionHas('error');
        SalesInvoiceRecord::query()->whereKey($id->toString())->update(['status' => 'paid']);
        $this->get('/sales/invoices/'.$id->toString())->assertOk()->assertSee('Betaald')->assertDontSee('Header bewerken')->assertDontSee('Factuur annuleren')->assertDontSee('Factuur boeken')->assertDontSee('Betaald markeren');
    }

    public function test_exact_permissions_do_not_imply_each_other_and_mutation_only_redirects_safely(): void
    {
        $this->assignPermissionOnly(SalesPermission::ManageInvoiceDrafts, 'DRAFT_ONLY', 7);
        $this->login();
        $this->get('/sales/invoices')->assertForbidden();
        $this->get('/sales/invoices/create')->assertOk()->assertDontSee('href="'.route('sales.invoices.index').'"', false);
        $this->post('/sales/invoices', ['customer_id' => self::CUSTOMER_A, 'invoice_date' => '2026-08-24', 'due_date' => '2026-09-24'])->assertRedirect('/app');
        $invoice = SalesInvoiceRecord::query()->firstOrFail();
        $this->post('/sales/invoices/'.$invoice->getAttribute('id').'/finalize')->assertForbidden();

        self::assertFalse(Route::has('sales.invoices.post'));
        self::assertFalse(Route::has('sales.invoices.paid'));
    }

    private function provision(): void
    {
        $user = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($user, new UserDisplayName('Invoice Web User'), new EmailAddress('invoice-web@example.com'), 'correct-secure-password');
        $administrations = new EloquentAdministrationRepository;
        $memberships = new EloquentAdministrationMembershipRepository;
        foreach ([[self::ADMIN_A, self::MEMBERSHIP_A, 'IWA'], [self::ADMIN_B, self::MEMBERSHIP_B, 'IWB']] as [$admin, $membership, $code]) {
            $administrations->save(new Administration($this->admin($admin), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active));
            $memberships->save(new AdministrationMembership(new AdministrationMembershipId(new Uuid($membership)), $user, $this->admin($admin), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
            $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin($admin));
        }
        $this->app->make(SalesAuthorizationProvisioner::class)->provision();
        $this->customer(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 'A', 'Customer <script>alert(1)</script>');
        $this->customer(self::ADMIN_B, self::CUSTOMER_B, self::ADDRESS_B, 'B', 'Customer B');
        $this->tax(self::ADMIN_A, self::TAX_ACTIVE, 'VAT21', 'Hoge btw', '21', 'output', 'active');
        $this->tax(self::ADMIN_A, self::TAX_INACTIVE, 'VAT09', 'Inactieve btw', '9', 'output', 'inactive');
        $this->tax(self::ADMIN_A, self::TAX_INPUT, 'INPUT', 'Inkoop btw', '21', 'input', 'active');
        $this->tax(self::ADMIN_B, self::TAX_B, 'VATB', 'Andere tenant', '21', 'output', 'active');
    }

    private function customer(string $admin, string $customer, string $address, string $code, string $name): void
    {
        $relation = str_replace('000000000004', '000000000014', $customer);
        RelationRecord::query()->create(['id' => $relation, 'administration_id' => $admin, 'code' => 'R-'.$code, 'display_name' => $name, 'active' => true]);
        CustomerRecord::query()->create(['id' => $customer, 'administration_id' => $admin, 'relation_id' => $relation, 'customer_number' => 'C-'.$code, 'active' => true]);
        RelationAddressRecord::query()->create(['address_id' => $address, 'administration_id' => $admin, 'relation_id' => $relation, 'address_type' => 'invoice', 'address_line_1' => $admin === self::ADMIN_A ? 'Invoice <script>alert(2)</script>' : 'Invoice street B', 'address_line_2' => null, 'postal_code' => '1234 AB', 'city' => 'Amsterdam', 'country_code' => 'NL', 'active' => true]);
    }

    private function tax(string $admin, string $id, string $code, string $name, string $rate, string $direction, string $status): void
    {
        TaxCodeRecord::query()->create(['id' => $id, 'administration_id' => $admin, 'code' => $code, 'name' => $name, 'rate' => $rate, 'direction' => $direction, 'status' => $status]);
    }

    private function invoice(string $admin, string $customer, string $address, int $sequence): SalesInvoiceId
    {
        $id = new SalesInvoiceId(new Uuid(sprintf('8d000000-0000-4000-8000-%012d', $sequence)));
        self::assertSame('Success', $this->app->make(CreateSalesInvoice::class)->execute($this->admin($admin), $id, new CustomerId(new Uuid($customer)), new AddressId(new Uuid($address)), new DateTimeImmutable('2026-08-24'), new DateTimeImmutable('2026-09-24'))->name);

        return $id;
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'invoice-web@example.com', 'password' => 'correct-secure-password'])->assertRedirect();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_A]);
    }

    private function assign(SalesRole $role, int $sequence): void
    {
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(new MembershipRoleId(new Uuid(sprintf('8c000000-0000-4000-8000-%012d', $sequence))), new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $role->id(), true));
    }

    private function assignPermissionOnly(SalesPermission $permission, string $code, int $sequence): void
    {
        $roleId = new RoleId(new Uuid(sprintf('8a000000-0000-4000-8000-%012d', $sequence)));
        (new EloquentRoleRepository)->save(new Role($roleId, new RoleCode($code), new RoleName('Permission only'), null, RoleStatus::Active));
        (new EloquentRolePermissionRepository)->save(new RolePermission(new RolePermissionId(new Uuid(sprintf('8b000000-0000-4000-8000-%012d', $sequence))), $roleId, $permission->id(), true));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(new MembershipRoleId(new Uuid(sprintf('8e000000-0000-4000-8000-%012d', $sequence))), new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $roleId, true));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}
