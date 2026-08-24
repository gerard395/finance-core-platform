<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Fiscal\TaxCodeCatalogueProvisioner;
use App\Application\Identity\ProvisionUserAccount;
use App\Application\Sales\AddOrderLine;
use App\Application\Sales\ConfirmOrder;
use App\Application\Sales\CreateOrder;
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
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OrderRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxCodeRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class OrderWebTest extends TestCase
{
    use RefreshDatabase;

    private const USER = 'a1000000-0000-4000-8000-000000000001';

    private const ADMIN_A = 'a1000000-0000-4000-8000-000000000002';

    private const ADMIN_B = 'b1000000-0000-4000-8000-000000000002';

    private const MEMBERSHIP_A = 'a1000000-0000-4000-8000-000000000003';

    private const MEMBERSHIP_B = 'b1000000-0000-4000-8000-000000000003';

    private const CUSTOMER_A = 'a1000000-0000-4000-8000-000000000004';

    private const CUSTOMER_B = 'b1000000-0000-4000-8000-000000000004';

    private const ADDRESS_A = 'a1000000-0000-4000-8000-000000000024';

    private const TAX_A = 'a1000000-0000-4000-8000-000000000034';

    private const TAX_B = 'b1000000-0000-4000-8000-000000000034';

    protected function setUp(): void
    {
        parent::setUp();
        $this->provision();
    }

    public function test_authorization_navigation_and_empty_states_follow_exact_permissions(): void
    {
        $this->get('/sales/orders')->assertRedirect('/login');
        $this->login();
        $this->get('/sales/orders')->assertForbidden();

        $this->assign(SalesRole::Viewer, 1);
        $this->get('/sales/orders')->assertOk()->assertSee('Nog geen orders.')->assertSee('Orders')->assertDontSee('Nieuwe order');
        $this->get('/sales/orders/create')->assertForbidden();
    }

    public function test_create_uses_active_tenant_customer_base_currency_and_server_owned_fields(): void
    {
        $this->assign(SalesRole::Editor, 2);
        $this->login();
        $this->get('/sales/orders/create')->assertOk()->assertSee('C-A')->assertDontSee('C-B')->assertDontSee('C-INACTIVE');
        $response = $this->post('/sales/orders', [
            'customer_id' => self::CUSTOMER_A,
            'order_date' => '2026-08-21',
            'expiry_date' => '2026-09-21',
            'administration_id' => self::ADMIN_B,
            'order_number' => 'HACKED',
            'currency' => 'USD',
            'status' => 'accepted',
            'source_quotation_id' => 'b1000000-0000-4000-8000-000000000099',
        ]);
        $order = OrderRecord::query()->firstOrFail();
        $response->assertRedirect('/sales/orders/'.$order->getAttribute('id'));
        self::assertSame('O000001', $order->getAttribute('order_number'));
        self::assertSame(self::ADMIN_A, $order->getAttribute('administration_id'));
        self::assertSame('EUR', $order->getAttribute('currency'));
        self::assertSame('draft', $order->getAttribute('status'));
        self::assertNull($order->getAttribute('source_quotation_id'));
        self::assertSame('Customer <script>alert(1)</script>', $order->getAttribute('customer_name_snapshot'));
        $this->get('/sales/orders/'.$order->getAttribute('id'))->assertOk()->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

        $this->post('/sales/orders', ['customer_id' => self::CUSTOMER_B, 'order_date' => '2026-08-21'])->assertSessionHasErrors('customer_id');
        CustomerRecord::query()->whereKey(self::CUSTOMER_A)->update(['active' => false]);
        $this->post('/sales/orders', ['customer_id' => self::CUSTOMER_A, 'order_date' => '2026-08-21'])->assertSessionHasErrors('customer_id');
    }

    public function test_index_detail_filtering_and_tenant_isolation_use_persisted_snapshots(): void
    {
        $this->assign(SalesRole::Editor, 3);
        $this->login();
        $a = $this->order(self::ADMIN_A, self::CUSTOMER_A, 1);
        $b = $this->order(self::ADMIN_B, self::CUSTOMER_B, 2);
        RelationRecord::query()->where('administration_id', self::ADMIN_A)->update(['display_name' => 'Live renamed customer']);

        $this->get('/sales/orders')->assertOk()->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('Customer B')->assertSee('O000001');
        $this->get('/sales/orders?q=absent')->assertOk()->assertSee('Geen orders gevonden met deze filters.');
        $this->get('/sales/orders?status=draft&sort=number&direction=asc&per_page=25')->assertOk()->assertSee('O000001');
        $this->get('/sales/orders/'.$a->toString())->assertOk()->assertSee('O000001')->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('Live renamed customer');
        $this->get('/sales/orders/'.$b->toString())->assertNotFound();
        $this->put('/sales/orders/'.$b->toString(), ['order_date' => '2026-08-22'])->assertNotFound();
        $this->get('/sales/orders/not-a-uuid')->assertNotFound();
    }

    public function test_draft_lines_preserve_exact_decimals_identity_currency_and_cross_document_scope(): void
    {
        $this->assign(SalesRole::Editor, 4);
        $this->login();
        $first = $this->order(self::ADMIN_A, self::CUSTOMER_A, 1);
        $second = $this->order(self::ADMIN_A, self::CUSTOMER_A, 2);
        $this->post('/sales/orders/'.$first->toString().'/lines', ['description' => '<b>Consultancy</b>', 'quantity' => '1.25', 'unit_price' => '1234.5678'])->assertRedirect();
        $line = DB::table('order_lines')->where('order_id', $first->toString())->first();
        self::assertNotNull($line);
        self::assertSame('1.25', $line->quantity);
        self::assertSame('1234.5678', $line->unit_price_amount);
        self::assertSame('EUR', $line->currency);
        $this->put('/sales/orders/'.$first->toString().'/lines/'.$line->id, ['description' => 'Updated line', 'quantity' => '0.1', 'unit_price' => '1.25', 'line_id' => 'b1000000-0000-4000-8000-000000000099', 'currency' => 'USD'])->assertRedirect();
        $this->assertDatabaseHas('order_lines', ['id' => $line->id, 'quantity' => '0.1', 'unit_price_amount' => '1.25', 'currency' => 'EUR']);
        $this->put('/sales/orders/'.$second->toString().'/lines/'.$line->id, ['description' => 'Attack', 'quantity' => '1', 'unit_price' => '1'])->assertNotFound();
        $this->delete('/sales/orders/'.$first->toString().'/lines/'.$line->id)->assertRedirect();
        $this->assertDatabaseMissing('order_lines', ['id' => $line->id]);
        $this->post('/sales/orders/'.$first->toString().'/lines', ['description' => 'x', 'quantity' => '0', 'unit_price' => '-1'])->assertSessionHasErrors(['description', 'quantity', 'unit_price']);
    }

    public function test_lifecycle_and_non_draft_edits_fail_safely_and_mutation_only_redirects_to_app(): void
    {
        $this->assign(SalesRole::Editor, 5);
        $this->login();
        $id = $this->order(self::ADMIN_A, self::CUSTOMER_A, 1);
        $this->app->make(AddOrderLine::class)->execute($this->admin(self::ADMIN_A), $id, $this->line(1));
        $this->post('/sales/orders/'.$id->toString().'/confirm')->assertRedirect('/sales/orders/'.$id->toString());
        $this->get('/sales/orders/'.$id->toString().'/edit')->assertStatus(409);
        $this->put('/sales/orders/'.$id->toString(), ['order_date' => '2026-08-22'])->assertRedirect()->assertSessionHas('error');
        $this->post('/sales/orders/'.$id->toString().'/confirm')->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->post('/sales/orders/'.$id->toString().'/cancel')->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $id->toString(), 'status' => 'cancelled']);

        self::assertFalse(Route::has('sales.orders.partially-invoiced'));
        self::assertFalse(Route::has('sales.orders.fully-invoiced'));
    }

    public function test_manage_permission_does_not_imply_view_and_redirects_after_mutation(): void
    {
        $roleId = new RoleId(new Uuid('f1000000-0000-4000-8000-000000000001'));
        (new EloquentRoleRepository)->save(new Role($roleId, new RoleCode('ORDER_ONLY'), new RoleName('Order only'), null, RoleStatus::Active));
        (new EloquentRolePermissionRepository)->save(new RolePermission(new RolePermissionId(new Uuid('f1000000-0000-4000-8000-000000000002')), $roleId, SalesPermission::ManageOrders->id(), true));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(new MembershipRoleId(new Uuid('f1000000-0000-4000-8000-000000000003')), new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $roleId, true));
        $this->login();

        $this->get('/sales/orders')->assertForbidden();
        $this->get('/sales/orders/create')->assertOk()->assertDontSee('href="'.route('sales.orders.index').'"', false);
        $this->post('/sales/orders', ['customer_id' => self::CUSTOMER_A, 'order_date' => '2026-08-21'])->assertRedirect('/app');
        $this->get('/sales/orders/d1000000-0000-4000-8000-000000000001/invoice/create')->assertForbidden();
    }

    public function test_order_invoice_web_flow_shows_exact_progress_replays_and_reaches_partial_then_full(): void
    {
        $this->assign(SalesRole::Manager, 10);
        $this->login();
        $this->invoiceCatalogue();
        $this->app->make(TaxCodeCatalogueProvisioner::class)->ensureDutchBasicOutputForAdministration($this->admin(self::ADMIN_A));
        $order = $this->order(self::ADMIN_A, self::CUSTOMER_A, 10);
        $line = $this->lineWithQuantity(10, '10', '<script>Service</script>');
        $secondLine = $this->lineWithQuantity(110, '5', 'Second service');
        $this->app->make(AddOrderLine::class)->execute($this->admin(self::ADMIN_A), $order, $line);
        $this->app->make(AddOrderLine::class)->execute($this->admin(self::ADMIN_A), $order, $secondLine);
        $this->post('/sales/orders/'.$order->toString().'/confirm')->assertRedirect();

        $this->get('/sales/orders/'.$order->toString())->assertOk()->assertSee('Factuur maken')->assertSee('Besteld')->assertSee('Gereserveerd')->assertSee('Gefactureerd')->assertSee('Beschikbaar')->assertSee('&lt;script&gt;Service&lt;/script&gt;', false)->assertDontSee('<script>Service</script>', false);
        $page = $this->get('/sales/orders/'.$order->toString().'/invoice/create')->assertOk()->assertSee('10')->assertSee('BTW21')->assertSee('BTW9')->assertSee('BTW0')->assertSee('Invoice &lt;b&gt;A&lt;/b&gt;', false)->assertDontSee('INPUT')->assertDontSee('BTW-B');
        $token = $page->viewData('draftRequestToken');
        self::assertIsString($token);
        $payload = $this->invoicePayload($token, $line->id()->toString(), '4');
        $payload['lines'][$secondLine->id()->toString()] = ['quantity' => '5', 'tax_code_id' => self::TAX_A];
        $this->post('/sales/orders/'.$order->toString().'/invoice', $payload)->assertRedirect();
        $invoice = SalesInvoiceRecord::query()->where('source_order_id', $order->toString())->firstOrFail();
        self::assertSame('draft', $invoice->getAttribute('status'));
        self::assertSame(self::ADMIN_A, $invoice->getAttribute('administration_id'));
        self::assertSame('Customer <script>alert(1)</script>', $invoice->getAttribute('customer_name_snapshot'));
        $this->assertDatabaseHas('order_invoice_reservations', ['sales_invoice_id' => $invoice->getAttribute('id'), 'order_line_id' => $line->id()->toString(), 'quantity' => '4']);
        $this->assertDatabaseHas('order_invoice_reservations', ['sales_invoice_id' => $invoice->getAttribute('id'), 'order_line_id' => $secondLine->id()->toString(), 'quantity' => '5']);
        $this->get('/sales/orders/'.$order->toString())->assertSee('Factuur maken');
        $this->get('/sales/invoices/'.$invoice->getAttribute('id'))->assertOk()->assertSee('Bekijk order')->assertDontSee('Regel toevoegen')->assertDontSee('Regel bewerken');

        $this->post('/sales/orders/'.$order->toString().'/invoice', $payload)->assertRedirect('/sales/invoices/'.$invoice->getAttribute('id'))->assertSessionHas('status', 'Deze factuur is al aangemaakt.');
        self::assertSame(1, SalesInvoiceRecord::query()->where('source_order_id', $order->toString())->count());
        $this->post('/sales/invoices/'.$invoice->getAttribute('id').'/finalize')->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->toString(), 'status' => 'partially_invoiced']);
        $this->assertDatabaseHas('order_invoice_allocations', ['sales_invoice_id' => $invoice->getAttribute('id'), 'quantity' => '4']);
        $this->assertDatabaseHas('order_invoice_allocations', ['sales_invoice_id' => $invoice->getAttribute('id'), 'order_line_id' => $secondLine->id()->toString(), 'quantity' => '5']);

        $secondPage = $this->get('/sales/orders/'.$order->toString().'/invoice/create')->assertOk()->assertSee('6');
        $secondPayload = $this->invoicePayload($secondPage->viewData('draftRequestToken'), $line->id()->toString(), '6');
        $this->post('/sales/orders/'.$order->toString().'/invoice', $secondPayload)->assertRedirect();
        $second = SalesInvoiceRecord::query()->where('source_order_id', $order->toString())->where('id', '!=', $invoice->getAttribute('id'))->firstOrFail();
        $this->post('/sales/invoices/'.$second->getAttribute('id').'/finalize')->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->toString(), 'status' => 'fully_invoiced']);
        $this->get('/sales/orders/'.$order->toString())->assertOk()->assertDontSee('Factuur maken');
        $this->get('/sales/orders/'.$order->toString().'/invoice/create')->assertStatus(409);
    }

    public function test_draft_cancel_restores_available_and_web_rejects_stale_tenant_tax_address_and_empty_input(): void
    {
        $this->assign(SalesRole::Manager, 11);
        $this->login();
        $this->invoiceCatalogue();
        $order = $this->order(self::ADMIN_A, self::CUSTOMER_A, 11);
        $line = $this->lineWithQuantity(11, '10', 'Cancel service');
        $this->app->make(AddOrderLine::class)->execute($this->admin(self::ADMIN_A), $order, $line);
        $this->post('/sales/orders/'.$order->toString().'/confirm');
        $page = $this->get('/sales/orders/'.$order->toString().'/invoice/create');
        $token = $page->viewData('draftRequestToken');

        $empty = $this->invoicePayload($token, $line->id()->toString(), '0');
        $this->post('/sales/orders/'.$order->toString().'/invoice', $empty)->assertSessionHasErrors('invoice');
        $foreignTax = $this->invoicePayload($token, $line->id()->toString(), '1');
        $foreignTax['lines'][$line->id()->toString()]['tax_code_id'] = self::TAX_B;
        $this->post('/sales/orders/'.$order->toString().'/invoice', $foreignTax)->assertSessionHasErrors('invoice');
        $foreignAddress = $this->invoicePayload($token, $line->id()->toString(), '1');
        $foreignAddress['invoice_address_id'] = 'b1000000-0000-4000-8000-000000000024';
        $this->post('/sales/orders/'.$order->toString().'/invoice', $foreignAddress)->assertSessionHasErrors('invoice');
        $stale = $this->invoicePayload($token, $line->id()->toString(), '11');
        $this->post('/sales/orders/'.$order->toString().'/invoice', $stale)->assertSessionHasErrors('invoice');
        $forged = $this->invoicePayload('forged', $line->id()->toString(), '1');
        $this->post('/sales/orders/'.$order->toString().'/invoice', $forged)->assertSessionHasErrors('draft_request_token');

        $this->post('/sales/orders/'.$order->toString().'/invoice', $this->invoicePayload($token, $line->id()->toString(), '6'))->assertRedirect();
        $invoice = SalesInvoiceRecord::query()->where('source_order_id', $order->toString())->firstOrFail();
        $this->get('/sales/orders/'.$order->toString())->assertSee('6')->assertSee('4');
        $this->post('/sales/invoices/'.$invoice->getAttribute('id').'/cancel')->assertRedirect();
        $this->assertDatabaseHas('order_invoice_reservation_releases', ['administration_id' => self::ADMIN_A]);
        $this->get('/sales/orders/'.$order->toString())->assertSee('10');

        $retryPage = $this->get('/sales/orders/'.$order->toString().'/invoice/create');
        DB::table('sales_number_sequences')->where('administration_id', self::ADMIN_A)->where('sequence_type', 'sales_invoice')->update(['active' => false]);
        $this->post('/sales/orders/'.$order->toString().'/invoice', $this->invoicePayload($retryPage->viewData('draftRequestToken'), $line->id()->toString(), '1'))->assertSessionHasErrors('invoice');
        DB::table('sales_number_sequences')->where('administration_id', self::ADMIN_A)->where('sequence_type', 'sales_invoice')->update(['active' => true]);
        TaxCodeRecord::query()->where('administration_id', self::ADMIN_A)->update(['status' => 'inactive']);
        $this->get('/sales/orders/'.$order->toString().'/invoice/create')->assertSee('Geen btw-codes beschikbaar.')->assertSee('disabled', false);
        RelationAddressRecord::query()->where('administration_id', self::ADMIN_A)->update(['active' => false]);
        $this->get('/sales/orders/'.$order->toString().'/invoice/create')->assertSee('Geen geldig factuuradres beschikbaar.');

        $other = $this->order(self::ADMIN_B, self::CUSTOMER_B, 12);
        $this->get('/sales/orders/'.$other->toString().'/invoice/create')->assertNotFound();
        $this->post('/sales/orders/not-a-uuid/invoice', $this->invoicePayload($token, $line->id()->toString(), '1'))->assertNotFound();
    }

    public function test_invoice_create_requires_exact_draft_permission_and_mutation_only_redirects_to_app(): void
    {
        $this->invoiceCatalogue();
        $order = $this->order(self::ADMIN_A, self::CUSTOMER_A, 13);
        $line = $this->lineWithQuantity(13, '2', 'Permission service');
        $this->app->make(AddOrderLine::class)->execute($this->admin(self::ADMIN_A), $order, $line);
        $this->app->make(ConfirmOrder::class)->execute($this->admin(self::ADMIN_A), $order);

        $this->assign(SalesRole::Viewer, 13);
        $this->login();
        $this->get('/sales/orders/'.$order->toString())->assertOk()->assertDontSee('Factuur maken');
        $this->get('/sales/orders/'.$order->toString().'/invoice/create')->assertForbidden();

        $roleId = new RoleId(new Uuid('f2000000-0000-4000-8000-000000000001'));
        (new EloquentRoleRepository)->save(new Role($roleId, new RoleCode('INVOICE_DRAFT_ONLY'), new RoleName('Invoice draft only'), null, RoleStatus::Active));
        (new EloquentRolePermissionRepository)->save(new RolePermission(new RolePermissionId(new Uuid('f2000000-0000-4000-8000-000000000002')), $roleId, SalesPermission::ManageInvoiceDrafts->id(), true));
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(new MembershipRoleId(new Uuid('f2000000-0000-4000-8000-000000000003')), new AdministrationMembershipId(new Uuid(self::MEMBERSHIP_A)), $roleId, true));
        DB::table('administration_membership_roles')->where('id', 'c1000000-0000-4000-8000-000000000013')->delete();
        $this->get('/sales/orders/'.$order->toString().'/invoice/create')->assertOk();
        $page = $this->get('/sales/orders/'.$order->toString().'/invoice/create');
        $this->post('/sales/orders/'.$order->toString().'/invoice', $this->invoicePayload($page->viewData('draftRequestToken'), $line->id()->toString(), '2'))->assertRedirect('/app');
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

    private function order(string $admin, string $customer, int $sequence): OrderId
    {
        $id = new OrderId(new Uuid(sprintf('d1000000-0000-4000-8000-%012d', $sequence)));
        self::assertSame('Success', $this->app->make(CreateOrder::class)->execute($this->admin($admin), $id, new CustomerId(new Uuid($customer)), new Currency('EUR'), new DateTimeImmutable('2026-08-21'))->name);

        return $id;
    }

    private function line(int $sequence): OrderLine
    {
        return new OrderLine(new OrderLineId(new Uuid(sprintf('e1000000-0000-4000-8000-%012d', $sequence))), new LineDescription('Service line'), new Quantity('2'), new Money('10', new Currency('EUR')));
    }

    private function lineWithQuantity(int $sequence, string $quantity, string $description): OrderLine
    {
        return new OrderLine(new OrderLineId(new Uuid(sprintf('e2000000-0000-4000-8000-%012d', $sequence))), new LineDescription($description), new Quantity($quantity), new Money('10', new Currency('EUR')));
    }

    private function invoiceCatalogue(): void
    {
        RelationAddressRecord::query()->create(['address_id' => self::ADDRESS_A, 'administration_id' => self::ADMIN_A, 'relation_id' => 'a1000000-0000-4000-8000-000000000014', 'address_type' => 'invoice', 'address_line_1' => 'Invoice <b>A</b>', 'address_line_2' => null, 'postal_code' => '1234 AB', 'city' => 'Amsterdam', 'country_code' => 'NL', 'active' => true]);
        RelationAddressRecord::query()->create(['address_id' => 'b1000000-0000-4000-8000-000000000024', 'administration_id' => self::ADMIN_B, 'relation_id' => 'b1000000-0000-4000-8000-000000000014', 'address_type' => 'invoice', 'address_line_1' => 'Invoice B', 'address_line_2' => null, 'postal_code' => '1234 AB', 'city' => 'Breda', 'country_code' => 'NL', 'active' => true]);
        TaxCodeRecord::query()->create(['id' => self::TAX_A, 'administration_id' => self::ADMIN_A, 'code' => 'BTW21', 'name' => '<b>Output</b>', 'rate' => '21', 'direction' => 'output', 'status' => 'active']);
        TaxCodeRecord::query()->create(['id' => self::TAX_B, 'administration_id' => self::ADMIN_B, 'code' => 'BTW-B', 'name' => 'Foreign', 'rate' => '21', 'direction' => 'output', 'status' => 'active']);
        TaxCodeRecord::query()->create(['id' => 'a1000000-0000-4000-8000-000000000035', 'administration_id' => self::ADMIN_A, 'code' => 'INPUT', 'name' => 'Input', 'rate' => '21', 'direction' => 'input', 'status' => 'active']);
    }

    private function invoicePayload(string $token, string $lineId, string $quantity): array
    {
        return ['draft_request_token' => $token, 'invoice_date' => '2026-08-24', 'due_date' => '2026-09-24', 'invoice_address_id' => self::ADDRESS_A, 'administration_id' => self::ADMIN_B, 'customer_id' => self::CUSTOMER_B, 'currency' => 'USD', 'lines' => [$lineId => ['quantity' => $quantity, 'tax_code_id' => self::TAX_A, 'description' => 'HACKED', 'unit_price' => '0']]];
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}
