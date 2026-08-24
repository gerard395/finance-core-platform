<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Fiscal\TaxCodeCatalogueProvisioner;
use App\Application\Identity\ProvisionUserAccount;
use App\Application\Sales\CreateSalesCreditInvoiceFromInvoice;
use App\Application\Sales\CreateSalesInvoice;
use App\Application\Sales\PostSalesInvoice;
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
use App\Http\Middleware\EnsureSalesPermission;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoiceRecord;
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

    public function test_authorization_navigation_empty_states_and_no_paid_route(): void
    {
        $this->get('/sales/invoices')->assertRedirect('/login');
        $this->login();
        $this->get('/sales/invoices')->assertForbidden();
        $this->assign(SalesRole::Viewer, 1);
        $this->get('/sales/invoices')->assertOk()->assertSee('Nog geen verkoopfacturen.')->assertSee('Facturen')->assertDontSee('Nieuwe factuur');
        $this->get('/sales/invoices/create')->assertForbidden();
        self::assertTrue(Route::has('sales.invoices.post'));
        self::assertFalse(Route::has('sales.invoices.paid'));
        $this->post('/sales/invoices/not-a-uuid/post')->assertForbidden();
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
        $this->app->make(TaxCodeCatalogueProvisioner::class)->ensureDutchBasicOutputForAdministration($this->admin(self::ADMIN_A));
        $this->get('/sales/invoices/'.$id->toString())->assertOk()->assertSee('VAT21')->assertSee('Hoge btw')->assertSee('BTW21')->assertSee('BTW9')->assertSee('BTW0')->assertSee('Btw verlegd - dienst EU')->assertSee('Intracommunautaire levering goederen')->assertSee('Buiten Nederlandse btw-heffing')->assertSee('Vrijgesteld')->assertDontSee('VAT09')->assertDontSee('INPUT')->assertDontSee('VATB')->assertDontSee('name="tax_rate"', false);
        $this->get('/sales/invoices/create')->assertOk()->assertSee('Prestatiedatum')->assertSee('name="supply_date"', false);
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

        self::assertTrue(Route::has('sales.invoices.post'));
        self::assertFalse(Route::has('sales.invoices.paid'));
    }

    public function test_view_issue_and_manager_permissions_cannot_post_without_post_permission(): void
    {
        $invoice = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 20);
        $this->assign(SalesRole::Viewer, 20);
        $this->login();
        $this->get('/sales/invoices/'.$invoice->toString())->assertOk()->assertDontSee('Factuur boeken');
        $this->post('/sales/invoices/'.$invoice->toString().'/post')->assertForbidden();

        $this->assignPermissionOnly(SalesPermission::IssueInvoices, 'ISSUE_ONLY', 21);
        $this->post('/sales/invoices/'.$invoice->toString().'/post')->assertForbidden();
        $this->assign(SalesRole::Manager, 22);
        $this->post('/sales/invoices/'.$invoice->toString().'/post')->assertForbidden();
    }

    public function test_poster_without_view_can_post_from_context_and_redirects_to_app(): void
    {
        $invoice = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 21);
        $this->assign(SalesRole::Poster, 23);
        $this->login();

        $this->post('/sales/invoices/'.$invoice->toString().'/post', ['administration_id' => self::ADMIN_B])
            ->assertRedirect('/app')
            ->assertSessionHas('status', 'Factuur is geboekt.');

        $this->assertDatabaseHas('sales_invoices', ['id' => $invoice->toString(), 'administration_id' => self::ADMIN_A, 'status' => 'posted']);
        $this->assertDatabaseHas('journal_entries', ['administration_id' => self::ADMIN_A, 'journal_id' => $this->journalId(1)]);
        $this->assertDatabaseHas('sales_invoice_postings', ['administration_id' => self::ADMIN_A, 'sales_invoice_id' => $invoice->toString()]);
    }

    public function test_post_button_is_visible_only_for_finalized_invoice_with_permission(): void
    {
        $this->assign(SalesRole::Viewer, 24);
        $this->assign(SalesRole::Poster, 25);
        $finalized = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 22);
        $draft = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 23, 'draft');
        $posted = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 24, 'posted');
        $paid = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 25, 'paid');
        $cancelled = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 26, 'cancelled');
        $this->login();

        $this->get('/sales/invoices/'.$finalized->toString())->assertOk()->assertSee('Factuur boeken');
        foreach ([$draft, $posted, $paid, $cancelled] as $invoice) {
            $this->get('/sales/invoices/'.$invoice->toString())->assertOk()->assertDontSee('Factuur boeken');
        }
        $this->get('/sales/invoices/'.$paid->toString())->assertDontSee('Betaald markeren');
    }

    public function test_success_redirects_to_detail_and_second_post_is_safe_and_idempotent(): void
    {
        $this->assign(SalesRole::Viewer, 26);
        $this->assign(SalesRole::Poster, 27);
        $invoice = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 27);
        $this->login();

        $this->post('/sales/invoices/'.$invoice->toString().'/post')
            ->assertRedirect('/sales/invoices/'.$invoice->toString())
            ->assertSessionHas('status', 'Factuur is geboekt.');
        $counts = $this->postingCounts();
        $this->get('/sales/invoices/'.$invoice->toString())->assertOk()->assertSee('Geboekt')->assertDontSee('Factuur boeken')->assertDontSee('Betaald markeren');

        $this->post('/sales/invoices/'.$invoice->toString().'/post')
            ->assertRedirect('/sales/invoices/'.$invoice->toString())
            ->assertSessionHas('status', 'Deze factuur is al geboekt.');
        self::assertSame($counts, $this->postingCounts());
    }

    public function test_typed_safe_error_messages_cover_configuration_state_and_inconsistency(): void
    {
        $this->assign(SalesRole::Viewer, 28);
        $this->assign(SalesRole::Poster, 29);
        $missing = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 28);
        $invalid = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 29);
        $draft = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 30, 'draft');
        $inconsistent = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 31, 'posted');
        $this->login();

        DB::table('sales_posting_configurations')->where('administration_id', self::ADMIN_A)->delete();
        $this->post('/sales/invoices/'.$missing->toString().'/post')->assertSessionHas('error', 'De verkoopboekingsconfiguratie is nog niet volledig ingesteld.');
        $this->postingConfiguration(self::ADMIN_A, 1);
        DB::table('journals')->where('id', $this->journalId(1))->update(['status' => 'inactive']);
        $this->post('/sales/invoices/'.$invalid->toString().'/post')->assertSessionHas('error', 'De verkoopboekingsconfiguratie is ongeldig of niet meer beschikbaar.');
        DB::table('journals')->where('id', $this->journalId(1))->update(['status' => 'active']);
        $this->post('/sales/invoices/'.$draft->toString().'/post')->assertSessionHas('error', 'Deze factuur kan in de huidige status niet worden geboekt.');
        $this->post('/sales/invoices/'.$inconsistent->toString().'/post')->assertSessionHas('error', 'De financiële status van deze factuur is niet consistent. Controle is vereist.');
        self::assertSame([0, 0, 0, 0], $this->postingCounts());
    }

    public function test_post_route_is_tenant_isolated_revocable_and_ignores_body_administration(): void
    {
        $assignmentSequence = 30;
        $this->assign(SalesRole::Poster, $assignmentSequence);
        $invoiceA = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 32);
        $invoiceAAfterRevocation = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 33);
        $invoiceB = $this->finalizedInvoice(self::ADMIN_B, self::CUSTOMER_B, self::ADDRESS_B, 34);
        $this->login();

        $this->post('/sales/invoices/'.$invoiceB->toString().'/post')->assertNotFound();
        $this->post('/sales/invoices/not-a-uuid/post')->assertNotFound();
        $this->post('/sales/invoices/'.$invoiceA->toString().'/post', ['administration_id' => self::ADMIN_B])->assertRedirect('/app');
        $this->assertDatabaseHas('sales_invoice_postings', ['administration_id' => self::ADMIN_A, 'sales_invoice_id' => $invoiceA->toString()]);

        $assignmentId = new MembershipRoleId(new Uuid(sprintf('8c000000-0000-4000-8000-%012d', $assignmentSequence)));
        $repository = new EloquentMembershipRoleRepository;
        $assignment = $repository->findById($assignmentId);
        self::assertNotNull($assignment);
        $assignment->deactivate();
        $repository->save($assignment);
        $this->post('/sales/invoices/'.$invoiceAAfterRevocation->toString().'/post')->assertForbidden();

        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN_B]);
        $this->post('/sales/invoices/'.$invoiceB->toString().'/post')->assertForbidden();
    }

    public function test_post_route_is_post_only_csrf_protected_and_has_no_paid_peer(): void
    {
        $route = Route::getRoutes()->getByName('sales.invoices.post');
        self::assertNotNull($route);
        self::assertSame(['POST'], $route->methods());
        self::assertContains('web', $route->gatherMiddleware());
        self::assertContains(EnsureSalesPermission::using(SalesPermission::PostInvoices), $route->gatherMiddleware());
        self::assertFalse(Route::has('sales.invoices.paid'));

        $this->get('/sales/invoices/8d000000-0000-4000-8000-000000000001/post')->assertMethodNotAllowed();
    }

    public function test_posting_presentation_contains_no_financial_persistence_or_calculation_logic(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/Sales/SalesInvoicePostingController.php'));
        $blade = (string) file_get_contents(resource_path('views/sales/invoices/show.blade.php'));
        foreach (['Eloquent', 'JournalEntryStore', 'TaxPostingStore', 'OpenItemStore', 'PostingEngine', 'SalesPostingConfigurationReader'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller);
            self::assertStringNotContainsString($forbidden, $blade);
        }
        self::assertStringNotContainsString('markPaid', $controller.$blade);
        self::assertStringNotContainsString('Betaald markeren', $blade);
    }

    public function test_credit_web_authorization_navigation_routes_and_empty_states_are_exact(): void
    {
        $this->get('/sales/credit-invoices')->assertRedirect('/login');
        $this->login();
        $this->get('/sales/credit-invoices')->assertForbidden();
        $this->assign(SalesRole::Viewer, 40);
        $this->get('/sales/credit-invoices')->assertOk()->assertSee('Nog geen creditfacturen.')->assertSee('Offertes')->assertSee('Orders')->assertSee('Facturen')->assertSee('Creditfacturen')->assertDontSee('Nieuwe creditfactuur');
        $this->get('/sales/credit-invoices/create')->assertForbidden();
        foreach (['index', 'create', 'store', 'show', 'finalize', 'cancel'] as $name) {
            self::assertTrue(Route::has('sales.credit-invoices.'.$name));
        }
        foreach (['edit', 'lines.store', 'lines.update', 'lines.destroy', 'paid', 'refund'] as $name) {
            self::assertFalse(Route::has('sales.credit-invoices.'.$name));
        }
        self::assertTrue(Route::has('sales.credit-invoices.post'));
        self::assertSame(['POST'], Route::getRoutes()->getByName('sales.credit-invoices.store')?->methods());
        self::assertSame(['POST'], Route::getRoutes()->getByName('sales.credit-invoices.finalize')?->methods());
        self::assertSame(['POST'], Route::getRoutes()->getByName('sales.credit-invoices.cancel')?->methods());
    }

    public function test_credit_selector_create_and_detail_use_only_historical_full_source_truth(): void
    {
        $this->assign(SalesRole::Editor, 41);
        $this->login();
        $posted = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 40);
        $paid = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 41);
        SalesInvoiceRecord::query()->whereKey($paid->toString())->update(['status' => 'paid']);
        $hiddenDraft = $this->finalizedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 42, 'draft');
        $hiddenB = $this->postedInvoice(self::ADMIN_B, self::CUSTOMER_B, self::ADDRESS_B, 43);
        RelationRecord::query()->where('administration_id', self::ADMIN_A)->update(['display_name' => 'Later renamed']);
        CustomerRecord::query()->where('administration_id', self::ADMIN_A)->update(['active' => false]);

        $this->get('/sales/credit-invoices/create')->assertOk()->assertSee('F000001')->assertSee('F000002')->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Geboekt')->assertSee('Betaald')->assertSee('EUR 121,00')->assertDontSee($hiddenDraft->toString())->assertDontSee($hiddenB->toString())->assertSee('Gedeeltelijke credits en regelwijzigingen worden niet ondersteund.')->assertDontSee('credit_amount')->assertDontSee('quantity_override');
        $response = $this->post('/sales/credit-invoices', ['source_invoice_id' => $posted->toString(), 'credit_date' => '2026-08-24', 'administration_id' => self::ADMIN_B, 'customer_id' => self::CUSTOMER_B, 'currency' => 'USD', 'status' => 'posted', 'lines' => [['amount' => '1']], 'tax_rate' => '0']);
        $credit = SalesCreditInvoiceRecord::query()->firstOrFail();
        $response->assertRedirect('/sales/credit-invoices/'.$credit->getAttribute('id'));
        self::assertSame(self::ADMIN_A, $credit->getAttribute('administration_id'));
        self::assertSame($posted->toString(), $credit->getAttribute('source_sales_invoice_id'));
        self::assertSame(self::CUSTOMER_A, $credit->getAttribute('customer_id'));
        self::assertSame('Customer <script>alert(1)</script>', $credit->getAttribute('customer_name_snapshot'));
        self::assertSame('EUR', $credit->getAttribute('currency'));
        self::assertSame('draft', $credit->getAttribute('status'));
        self::assertSame('C000001', $credit->getAttribute('sales_credit_invoice_number'));
        $this->assertDatabaseHas('sales_credit_invoice_lines', ['sales_credit_invoice_id' => $credit->getAttribute('id'), 'description' => 'Posting service', 'unit_price_amount' => '100']);
        $this->get('/sales/credit-invoices/'.$credit->getAttribute('id'))->assertOk()->assertSee('F000001')->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Invoice &lt;script&gt;alert(2)&lt;/script&gt;', false)->assertSee('Posting service')->assertSee('EUR 100,00')->assertDontSee('<script>', false)->assertDontSee('Regel toevoegen')->assertDontSee('Regel bewerken')->assertDontSee('Creditfactuur boeken');

        $this->post('/sales/credit-invoices', ['source_invoice_id' => $paid->toString(), 'credit_date' => '2026-08-25'])->assertRedirect();
        $this->assertDatabaseHas('sales_credit_invoices', ['administration_id' => self::ADMIN_A, 'source_sales_invoice_id' => $paid->toString(), 'sales_credit_invoice_number' => 'C000002', 'status' => 'draft']);
    }

    public function test_credit_create_revalidates_stale_invalid_and_cross_tenant_sources_safely(): void
    {
        $this->assign(SalesRole::Editor, 42);
        $this->login();
        $credited = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 44);
        $this->post('/sales/credit-invoices', ['source_invoice_id' => $credited->toString(), 'credit_date' => '2026-08-24'])->assertRedirect();
        $count = SalesCreditInvoiceRecord::query()->count();
        $next = DB::table('sales_number_sequences')->where('administration_id', self::ADMIN_A)->where('sequence_type', 'sales_credit_invoice')->value('next_value');
        $this->post('/sales/credit-invoices', ['source_invoice_id' => $credited->toString(), 'credit_date' => '2026-08-24'])->assertSessionHasErrors(['source_invoice_id' => 'Deze factuur kan niet worden gecrediteerd.']);
        self::assertSame($count, SalesCreditInvoiceRecord::query()->count());
        self::assertSame($next, DB::table('sales_number_sequences')->where('administration_id', self::ADMIN_A)->where('sequence_type', 'sales_credit_invoice')->value('next_value'));

        $invalid = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 45);
        DB::table('sales_invoice_postings')->where('sales_invoice_id', $invalid->toString())->delete();
        $this->get('/sales/credit-invoices/create')->assertOk()->assertDontSee($invalid->toString());
        $this->post('/sales/credit-invoices', ['source_invoice_id' => $invalid->toString(), 'credit_date' => '2026-08-24'])->assertSessionHasErrors(['source_invoice_id' => 'De factuur heeft geen consistente financiële broninformatie.']);
        $crossTenant = $this->postedInvoice(self::ADMIN_B, self::CUSTOMER_B, self::ADDRESS_B, 46);
        $this->post('/sales/credit-invoices', ['source_invoice_id' => $crossTenant->toString(), 'credit_date' => '2026-08-24', 'administration_id' => self::ADMIN_B])->assertNotFound();
        $this->post('/sales/credit-invoices', ['source_invoice_id' => 'not-a-uuid', 'credit_date' => '2026-08-24'])->assertSessionHasErrors('source_invoice_id');
    }

    public function test_credit_index_detail_lifecycle_permissions_and_mutation_only_redirects_are_tenant_safe(): void
    {
        $this->assign(SalesRole::Manager, 43);
        $this->login();
        $sourceA = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 47);
        $sourceB = $this->postedInvoice(self::ADMIN_B, self::CUSTOMER_B, self::ADDRESS_B, 48);
        $resultA = $this->app->make(CreateSalesCreditInvoiceFromInvoice::class)->execute($this->admin(self::ADMIN_A), $sourceA, new DateTimeImmutable('2026-08-24'));
        $resultB = $this->app->make(CreateSalesCreditInvoiceFromInvoice::class)->execute($this->admin(self::ADMIN_B), $sourceB, new DateTimeImmutable('2026-08-24'));
        $idA = $resultA->creditInvoiceId();
        $idB = $resultB->creditInvoiceId();
        self::assertNotNull($idA);
        self::assertNotNull($idB);
        $this->get('/sales/credit-invoices')->assertOk()->assertSee('C000001')->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('Customer B');
        $this->get('/sales/credit-invoices?q=absent')->assertOk()->assertSee('Geen creditfacturen gevonden.');
        $this->get('/sales/credit-invoices?status=draft&date_from=2026-08-01&date_to=2026-08-31&sort=number&direction=asc')->assertOk()->assertSee('C000001');
        $this->get('/sales/credit-invoices/'.$idB->toString())->assertNotFound();
        $this->get('/sales/credit-invoices/not-a-uuid')->assertNotFound();
        $this->post('/sales/credit-invoices/'.$idA->toString().'/finalize')->assertRedirect('/sales/credit-invoices/'.$idA->toString());
        $this->assertDatabaseHas('sales_credit_invoices', ['id' => $idA->toString(), 'status' => 'finalized']);
        $this->get('/sales/credit-invoices/'.$idA->toString())->assertOk()->assertSee('Definitief')->assertSee('Creditfactuur annuleren')->assertDontSee('Creditfactuur definitief maken')->assertDontSee('Creditfactuur boeken');
        $this->post('/sales/credit-invoices/'.$idA->toString().'/cancel')->assertRedirect();
        $this->assertDatabaseHas('sales_credit_invoices', ['id' => $idA->toString(), 'status' => 'cancelled']);
        DB::table('sales_credit_invoices')->where('id', $idA->toString())->update(['status' => 'posted']);
        $this->get('/sales/credit-invoices/'.$idA->toString())->assertOk()->assertSee('Geboekt')->assertDontSee('Creditfactuur annuleren')->assertDontSee('Creditfactuur boeken');
        $this->post('/sales/credit-invoices/'.$idA->toString().'/cancel')->assertRedirect()->assertSessionHas('error');
    }

    public function test_credit_permissions_are_independent_and_mutation_only_success_redirects_to_app(): void
    {
        $source = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 49);
        $this->assignPermissionOnly(SalesPermission::ManageCreditInvoiceDrafts, 'CREDIT_DRAFT_ONLY', 44);
        $this->login();
        $this->get('/sales/credit-invoices')->assertForbidden();
        $this->get('/sales/credit-invoices/create')->assertOk();
        $this->post('/sales/credit-invoices', ['source_invoice_id' => $source->toString(), 'credit_date' => '2026-08-24'])->assertRedirect('/app');
        $credit = SalesCreditInvoiceRecord::query()->firstOrFail();
        $this->post('/sales/credit-invoices/'.$credit->getAttribute('id').'/finalize')->assertForbidden();
        $this->assignPermissionOnly(SalesPermission::IssueCreditInvoices, 'CREDIT_ISSUE_ONLY', 45);
        $this->post('/sales/credit-invoices/'.$credit->getAttribute('id').'/finalize')->assertRedirect('/app');
        self::assertTrue(Route::has('sales.credit-invoices.post'));
        $this->post('/sales/credit-invoices/'.$credit->getAttribute('id').'/post')->assertForbidden();
    }

    public function test_credit_post_permission_controls_button_and_posts_with_view_redirect_idempotently(): void
    {
        $this->assign(SalesRole::Viewer, 46);
        $this->assign(SalesRole::Poster, 47);
        $this->login();
        $source = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 50);
        $created = $this->app->make(CreateSalesCreditInvoiceFromInvoice::class)->execute($this->admin(self::ADMIN_A), $source, new DateTimeImmutable('2026-08-24'));
        $creditId = $created->creditInvoiceId();
        self::assertNotNull($creditId);
        SalesCreditInvoiceRecord::query()->whereKey($creditId->toString())->update(['status' => 'finalized']);

        $this->get('/sales/credit-invoices/'.$creditId->toString())->assertOk()->assertSee('Creditfactuur boeken');
        $this->post('/sales/credit-invoices/'.$creditId->toString().'/post')->assertRedirect('/sales/credit-invoices/'.$creditId->toString())->assertSessionHas('status', 'Creditfactuur is geboekt.');
        $this->assertDatabaseHas('sales_credit_invoices', ['id' => $creditId->toString(), 'status' => 'posted']);
        $counts = $this->creditPostingCounts();
        $this->get('/sales/credit-invoices/'.$creditId->toString())->assertOk()->assertDontSee('Creditfactuur boeken');
        $this->post('/sales/credit-invoices/'.$creditId->toString().'/post')->assertRedirect('/sales/credit-invoices/'.$creditId->toString())->assertSessionHas('status', 'Deze creditfactuur is al geboekt.');
        self::assertSame($counts, $this->creditPostingCounts());
    }

    public function test_credit_post_permission_is_independent_tenant_safe_revocable_and_redirects_without_view(): void
    {
        $this->assignPermissionOnly(SalesPermission::PostCreditInvoices, 'CREDIT_POST_ONLY', 48);
        $this->login();
        $sourceA = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, 51);
        $sourceB = $this->postedInvoice(self::ADMIN_B, self::CUSTOMER_B, self::ADDRESS_B, 52);
        $creditA = $this->app->make(CreateSalesCreditInvoiceFromInvoice::class)->execute($this->admin(self::ADMIN_A), $sourceA, new DateTimeImmutable('2026-08-24'))->creditInvoiceId();
        $creditB = $this->app->make(CreateSalesCreditInvoiceFromInvoice::class)->execute($this->admin(self::ADMIN_B), $sourceB, new DateTimeImmutable('2026-08-24'))->creditInvoiceId();
        self::assertNotNull($creditA);
        self::assertNotNull($creditB);
        SalesCreditInvoiceRecord::query()->whereIn('id', [$creditA->toString(), $creditB->toString()])->update(['status' => 'finalized']);

        $this->post('/sales/credit-invoices/'.$creditB->toString().'/post')->assertNotFound();
        $this->post('/sales/credit-invoices/not-a-uuid/post')->assertNotFound();
        $this->post('/sales/credit-invoices/'.$creditA->toString().'/post', ['administration_id' => self::ADMIN_B])->assertRedirect('/app');

        $assignmentId = new MembershipRoleId(new Uuid(sprintf('8e000000-0000-4000-8000-%012d', 48)));
        $assignment = (new EloquentMembershipRoleRepository)->findById($assignmentId);
        self::assertNotNull($assignment);
        $assignment->deactivate();
        (new EloquentMembershipRoleRepository)->save($assignment);
        $this->post('/sales/credit-invoices/'.$creditA->toString().'/post')->assertForbidden();
    }

    public function test_credit_post_route_is_post_only_and_presentation_has_no_financial_logic(): void
    {
        $route = Route::getRoutes()->getByName('sales.credit-invoices.post');
        self::assertNotNull($route);
        self::assertSame(['POST'], $route->methods());
        self::assertContains('web', $route->gatherMiddleware());
        self::assertContains(EnsureSalesPermission::using(SalesPermission::PostCreditInvoices), $route->gatherMiddleware());
        self::assertFalse(Route::has('sales.credit-invoices.refund'));
        self::assertFalse(Route::has('sales.credit-invoices.partial'));
        $this->get('/sales/credit-invoices/8d000000-0000-4000-8000-000000000001/post')->assertMethodNotAllowed();

        $presentation = (string) file_get_contents(app_path('Http/Controllers/Sales/SalesCreditInvoicePostingController.php')).(string) file_get_contents(resource_path('views/sales/credit-invoices/show.blade.php'));
        foreach (['Eloquent', 'JournalEntryStore', 'TaxPostingStore', 'OpenItemStore', 'MatchOpenItems', 'PostingEngine'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $presentation);
        }
        self::assertStringNotContainsString('refund', strtolower($presentation));
        self::assertStringNotContainsString('partial', strtolower($presentation));
    }

    public function test_credit_post_typed_failures_are_safe_and_leak_no_financial_details(): void
    {
        $this->assignPermissionOnly(SalesPermission::PostCreditInvoices, 'CREDIT_POST_ERRORS', 49);
        $this->login();

        $draft = $this->creditFromPostedSource(53, false);
        $this->post('/sales/credit-invoices/'.$draft.'/post')->assertSessionHas('error', 'Deze creditfactuur kan in de huidige status niet worden geboekt.');

        $missing = $this->creditFromPostedSource(54);
        DB::table('sales_posting_configurations')->where('administration_id', self::ADMIN_A)->delete();
        $this->post('/sales/credit-invoices/'.$missing.'/post')->assertSessionHas('error', 'De verkoopboekingsconfiguratie is nog niet volledig ingesteld.');
        $this->postingConfiguration(self::ADMIN_A, 1);

        $invalid = $this->creditFromPostedSource(55);
        DB::table('journals')->where('id', $this->journalId(1))->update(['status' => 'inactive']);
        $this->post('/sales/credit-invoices/'.$invalid.'/post')->assertSessionHas('error', 'De verkoopboekingsconfiguratie is ongeldig of niet meer beschikbaar.');
        DB::table('journals')->where('id', $this->journalId(1))->update(['status' => 'active']);

        $sourceInvalid = $this->creditFromPostedSource(56);
        $sourceId = SalesCreditInvoiceRecord::query()->whereKey($sourceInvalid)->value('source_sales_invoice_id');
        DB::table('sales_invoice_postings')->where('sales_invoice_id', $sourceId)->delete();
        $this->post('/sales/credit-invoices/'.$sourceInvalid.'/post')->assertSessionHas('error', 'De oorspronkelijke factuur kan financieel niet veilig worden gecrediteerd.');

        $inconsistent = $this->creditFromPostedSource(57);
        SalesCreditInvoiceRecord::query()->whereKey($inconsistent)->update(['status' => 'posted']);
        $this->post('/sales/credit-invoices/'.$inconsistent.'/post')->assertSessionHas('error', 'De financiële status van deze creditfactuur is niet consistent. Controle is vereist.');
        self::assertSame(0, DB::table('sales_credit_invoice_postings')->count());
    }

    private function creditPostingCounts(): array
    {
        return [
            DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(),
            DB::table('open_item_matches')->count(), DB::table('sales_credit_invoice_postings')->count(),
        ];
    }

    private function creditFromPostedSource(int $sequence, bool $finalized = true): string
    {
        $source = $this->postedInvoice(self::ADMIN_A, self::CUSTOMER_A, self::ADDRESS_A, $sequence);
        $credit = $this->app->make(CreateSalesCreditInvoiceFromInvoice::class)->execute($this->admin(self::ADMIN_A), $source, new DateTimeImmutable('2026-08-24'))->creditInvoiceId();
        self::assertNotNull($credit);
        if ($finalized) {
            SalesCreditInvoiceRecord::query()->whereKey($credit->toString())->update(['status' => 'finalized']);
        }

        return $credit->toString();
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
        $this->postingMasterdata(self::ADMIN_A, 1);
        $this->postingMasterdata(self::ADMIN_B, 2);
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
        TaxCodeRecord::query()->create(['id' => $id, 'administration_id' => $admin, 'code' => $code, 'name' => $name, 'rate' => $rate, 'direction' => $direction, 'status' => $status, 'treatment' => $rate === '0' ? 'zero_rated' : 'domestic_standard', 'vat_return_classification' => $rate === '0' ? 'domestic_zero_rated' : 'domestic_standard', 'icp_classification' => 'none']);
    }

    private function invoice(string $admin, string $customer, string $address, int $sequence): SalesInvoiceId
    {
        $id = new SalesInvoiceId(new Uuid(sprintf('8d000000-0000-4000-8000-%012d', $sequence)));
        self::assertSame('Success', $this->app->make(CreateSalesInvoice::class)->execute($this->admin($admin), $id, new CustomerId(new Uuid($customer)), new AddressId(new Uuid($address)), new DateTimeImmutable('2026-08-24'), new DateTimeImmutable('2026-09-24'))->name);

        return $id;
    }

    private function finalizedInvoice(string $admin, string $customer, string $address, int $sequence, string $status = 'finalized'): SalesInvoiceId
    {
        $invoice = $this->invoice($admin, $customer, $address, $sequence);
        $tenant = $admin === self::ADMIN_A ? 1 : 2;
        $tax = $tenant === 1 ? self::TAX_ACTIVE : self::TAX_B;
        DB::table('sales_invoice_lines')->insert(['id' => sprintf('8f000000-0000-4000-8000-%012d', $sequence), 'administration_id' => $admin, 'sales_invoice_id' => $invoice->toString(), 'description' => 'Posting service', 'quantity' => '1', 'unit_price_amount' => '100', 'currency' => 'EUR', 'tax_code_id_snapshot' => $tax, 'tax_code_snapshot' => 'VAT21', 'tax_name_snapshot' => 'Snapshot VAT', 'tax_rate_snapshot' => '21', 'tax_direction_snapshot' => 'output', 'tax_treatment_snapshot' => 'domestic_standard', 'vat_return_classification_snapshot' => 'domestic_standard', 'icp_classification_snapshot' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        SalesInvoiceRecord::query()->whereKey($invoice->toString())->update(['status' => $status]);

        return $invoice;
    }

    private function postedInvoice(string $admin, string $customer, string $address, int $sequence): SalesInvoiceId
    {
        $invoice = $this->finalizedInvoice($admin, $customer, $address, $sequence);
        self::assertSame('Success', $this->app->make(PostSalesInvoice::class)->execute($this->admin($admin), $invoice)->status()->name);

        return $invoice;
    }

    private function postingMasterdata(string $administration, int $tenant): void
    {
        DB::table('journals')->insert(['id' => $this->journalId($tenant), 'administration_id' => $administration, 'code' => 'WEBPOST', 'name' => 'Web posting journal', 'type' => 'sales', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1 => 'asset', 2 => 'revenue', 3 => 'liability'] as $sequence => $type) {
            DB::table('ledger_accounts')->insert(['id' => $this->accountId($tenant, $sequence), 'administration_id' => $administration, 'code' => 'WP'.$sequence, 'name' => 'Web posting account '.$sequence, 'type' => $type, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->postingConfiguration($administration, $tenant);
    }

    private function postingConfiguration(string $administration, int $tenant): void
    {
        DB::table('sales_posting_configurations')->updateOrInsert(['administration_id' => $administration], ['sales_journal_id' => $this->journalId($tenant), 'accounts_receivable_ledger_account_id' => $this->accountId($tenant, 1), 'revenue_ledger_account_id' => $this->accountId($tenant, 2), 'output_vat_ledger_account_id' => $this->accountId($tenant, 3), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function postingCounts(): array
    {
        return [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(), DB::table('sales_invoice_postings')->count()];
    }

    private function journalId(int $tenant): string
    {
        return sprintf('8%d000000-0000-4000-8000-000000000010', $tenant + 2);
    }

    private function accountId(int $tenant, int $sequence): string
    {
        return sprintf('8%d000000-0000-4000-8000-%012d', $tenant + 4, $sequence);
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
