<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\PurchasingAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PurchaseInvoiceWebTest extends TestCase
{
    use RefreshDatabase;

    private const string USER = '94000000-0000-4000-8000-000000000001';

    private const string ADMIN = '94000000-0000-4000-8000-000000000002';

    private const string MEMBERSHIP = '94000000-0000-4000-8000-000000000003';

    private const string RELATION = '94000000-0000-4000-8000-000000000004';

    private const string SUPPLIER = '94000000-0000-4000-8000-000000000005';

    private const string EXPENSE = '94000000-0000-4000-8000-000000000006';

    private const string AP = '94000000-0000-4000-8000-000000000007';

    private const string VAT = '94000000-0000-4000-8000-000000000008';

    private const string TAX = '94000000-0000-4000-8000-000000000009';

    private const string OUTPUT = '94000000-0000-4000-8000-000000000010';

    private const string JOURNAL = '94000000-0000-4000-8000-000000000011';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
        $this->createOpenAccountingPeriodFixture(self::ADMIN);
    }

    public function test_end_to_end_web_flow_escapes_snapshots_ignores_tenant_spoof_and_is_idempotent(): void
    {
        $this->assignAll();
        $this->login();
        $this->get('/purchasing/invoices')->assertOk()->assertSee('Nieuwe inkoopfactuur');
        $this->get('/purchasing/invoices/create')->assertOk()->assertSee('Supplier &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('4000 · Expense')->assertSee('INBTW21')->assertDontSee('OUTPUT')->assertDontSee('Revenue');
        $payload = $this->payload('SUP-001');
        $payload['administration_id'] = '95000000-0000-4000-8000-000000000001';
        $response = $this->post('/purchasing/invoices', $payload);
        $invoice = DB::table('purchase_invoices')->first();
        self::assertNotNull($invoice);
        $response->assertRedirect('/purchasing/invoices/'.$invoice->id);
        self::assertSame(self::ADMIN, $invoice->administration_id);
        self::assertSame('draft', $invoice->status);
        $this->get('/purchasing/invoices/'.$invoice->id)->assertOk()->assertSee('Supplier &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/purchasing/invoices/'.$invoice->id.'/edit')->assertOk();
        $updated = $this->payload('SUP-001');
        $updated['lines'][0]['description'] = 'Updated purchase';
        $this->put('/purchasing/invoices/'.$invoice->id, $updated)->assertRedirect('/purchasing/invoices/'.$invoice->id);
        self::assertSame('Updated purchase', DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('description'));
        $this->post('/purchasing/invoices', $payload)->assertSessionHasErrors('invoice');
        self::assertSame(1, DB::table('purchase_invoices')->count());
        $this->post('/purchasing/invoices/'.$invoice->id.'/finalize')->assertRedirect('/purchasing/invoices/'.$invoice->id);
        $this->post('/purchasing/invoices/'.$invoice->id.'/finalize')->assertRedirect();
        $this->post('/purchasing/invoices/'.$invoice->id.'/post', ['posting_date' => '2026-08-25'])->assertRedirect('/purchasing/invoices/'.$invoice->id);
        $this->post('/purchasing/invoices/'.$invoice->id.'/post', ['posting_date' => '2026-08-25'])->assertRedirect();
        self::assertSame(1, DB::table('purchase_invoice_postings')->count());
        self::assertSame(1, DB::table('journal_entries')->count());
        self::assertSame(1, DB::table('tax_postings')->count());
        self::assertSame(1, DB::table('open_items')->count());
        $this->get('/purchasing/invoices/'.$invoice->id)->assertOk()->assertSee('Crediteur / openstaande post')->assertSee('121,00')->assertDontSee('Bewerken')->assertDontSee('Annuleren');
        $sourceLine = DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('id');
        $this->get('/purchasing/credits')->assertOk()->assertSee('Nieuwe creditnota');
        $this->get('/purchasing/credits/create?source='.$invoice->id)->assertOk()->assertSee('Supplier &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Purchase');
        $creditPayload = ['administration_id' => '95000000-0000-4000-8000-000000000001', 'source_invoice_id' => $invoice->id, 'supplier_credit_invoice_number' => 'CR-001<script>', 'supplier_credit_date' => '2026-08-23', 'received_date' => '2026-08-24', 'source_line_ids' => [$sourceLine], 'amount' => '0', 'ledger_account_id' => self::AP];
        $creditResponse = $this->post('/purchasing/credits', $creditPayload);
        $credit = DB::table('purchase_credit_invoices')->first();
        self::assertNotNull($credit);
        self::assertSame(self::ADMIN, $credit->administration_id);
        $creditResponse->assertRedirect('/purchasing/credits/'.$credit->id);
        $this->get('/purchasing/credits/'.$credit->id)->assertOk()->assertSee('CR-001&lt;script&gt;', false)->assertDontSee('CR-001<script>', false);
        $creditPayload['supplier_credit_invoice_number'] = 'CR-001-EDIT';
        $this->put('/purchasing/credits/'.$credit->id, $creditPayload)->assertRedirect('/purchasing/credits/'.$credit->id);
        $this->post('/purchasing/credits/'.$credit->id.'/finalize')->assertRedirect('/purchasing/credits/'.$credit->id);
        $this->post('/purchasing/credits/'.$credit->id.'/post', ['posting_date' => '2026-08-25'])->assertRedirect('/purchasing/credits/'.$credit->id);
        self::assertSame('posted', DB::table('purchase_credit_invoices')->where('id', $credit->id)->value('status'));
        self::assertSame(1, DB::table('purchase_credit_invoice_postings')->count());
        self::assertSame(1, DB::table('purchase_credit_source_line_claims')->count());
        self::assertSame(1, DB::table('open_item_matches')->count());
        $this->get('/purchasing/credits/'.$credit->id)->assertOk()->assertSee('Automatisch verrekend')->assertSee('121,00')->assertSee('Leverancierscreditsaldo')->assertDontSee('Bewerken')->assertDontSee('Annuleren');
        $this->get('/purchasing/credits/not-a-uuid')->assertNotFound();
        $this->post('/purchasing/credits/not-a-uuid/post', ['posting_date' => '2026-08-25'])->assertNotFound();
        $this->post('/purchasing/invoices', $this->payload('SUP-002'));
        $cancelled = DB::table('purchase_invoices')->where('supplier_invoice_number', 'SUP-002')->first();
        self::assertNotNull($cancelled);
        $this->post('/purchasing/invoices/'.$cancelled->id.'/cancel')->assertRedirect('/purchasing/invoices/'.$cancelled->id);
        self::assertSame('cancelled', DB::table('purchase_invoices')->where('id', $cancelled->id)->value('status'));
        $this->get('/purchasing/invoices/not-a-uuid')->assertNotFound();
        $this->post('/purchasing/invoices/not-a-uuid/post', ['posting_date' => '2026-08-25'])->assertNotFound();
    }

    public function test_permissions_are_independent_and_runtime_membership_revocation_is_effective(): void
    {
        $this->login();
        $routes = [
            PurchasingPermission::View->value => ['/purchasing/invoices', 200],
            PurchasingPermission::ManageInvoiceDrafts->value => ['/purchasing/invoices/create', 200],
            PurchasingPermission::FinalizeInvoices->value => ['/purchasing/invoices/00000000-0000-4000-8000-000000000001/finalize', 404],
            PurchasingPermission::PostInvoices->value => ['/purchasing/invoices/00000000-0000-4000-8000-000000000001/post', 404],
        ];
        foreach (PurchasingPermission::cases() as $index => $permission) {
            DB::table('administration_membership_roles')->delete();
            $this->assignOnly($permission, $index + 1);
            foreach ($routes as $code => [$url, $allowed]) {
                $response = str_ends_with($url, '/finalize') || str_ends_with($url, '/post') ? $this->post($url, ['posting_date' => '2026-08-25']) : $this->get($url);
                $code === $permission->value ? $response->assertStatus($allowed) : $response->assertForbidden();
            }
        }
        DB::table('administration_membership_roles')->update(['active' => false]);
        $this->post('/purchasing/invoices/00000000-0000-4000-8000-000000000001/post', ['posting_date' => '2026-08-25'])->assertForbidden();
        DB::table('administration_memberships')->where('id', self::MEMBERSHIP)->update(['active' => false]);
        $this->get('/purchasing/invoices')->assertRedirect('/administrations/select');
    }

    public function test_purchase_credit_permissions_are_independent(): void
    {
        $this->login();
        $routes = [
            PurchasingPermission::View->value => ['/purchasing/credits', 'get', 200],
            PurchasingPermission::ManageCreditDrafts->value => ['/purchasing/credits/create', 'get', 200],
            PurchasingPermission::FinalizeCredits->value => ['/purchasing/credits/00000000-0000-4000-8000-000000000001/finalize', 'post', 404],
            PurchasingPermission::PostCredits->value => ['/purchasing/credits/00000000-0000-4000-8000-000000000001/post', 'post', 404],
        ];
        foreach ([PurchasingPermission::View, PurchasingPermission::ManageCreditDrafts, PurchasingPermission::FinalizeCredits, PurchasingPermission::PostCredits] as $index => $permission) {
            DB::table('administration_membership_roles')->delete();
            $this->assignOnly($permission, $index + 80);
            foreach ($routes as $code => [$url, $method, $allowed]) {
                $response = $method === 'get' ? $this->get($url) : $this->post($url, ['posting_date' => '2026-08-25']);
                $code === $permission->value ? $response->assertStatus($allowed) : $response->assertForbidden();
            }
        }
    }

    public function test_purchase_credit_post_feedback_describes_full_partial_and_zero_matching(): void
    {
        $this->assignAll();
        $this->login();

        $cases = [
            ['FULL', null, 'Creditnota is geboekt en volledig met de bronfactuur verrekend. Automatisch verrekend: EUR 121,00.'],
            ['PARTIAL', '40', 'Creditnota is geboekt en gedeeltelijk met de bronfactuur verrekend. Automatisch verrekend: EUR 81,00. Leverancierscreditsaldo: EUR 40,00.'],
            ['PAID', '121', 'Creditnota is geboekt. Er is geen bedrag automatisch met de bronfactuur verrekend. Leverancierscreditsaldo: EUR 121,00.'],
        ];

        foreach ($cases as $index => [$suffix, $settledAmount, $expectedMessage]) {
            $this->post('/purchasing/invoices', $this->payload('SUP-'.$suffix));
            $invoice = DB::table('purchase_invoices')->where('supplier_invoice_number', 'SUP-'.$suffix)->first();
            self::assertNotNull($invoice);
            $this->post('/purchasing/invoices/'.$invoice->id.'/finalize');
            $this->post('/purchasing/invoices/'.$invoice->id.'/post', ['posting_date' => '2026-08-25']);

            $sourceOpenItemId = DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoice->id)->value('open_item_id');
            $sourceJournalEntryId = DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoice->id)->value('journal_entry_id');
            if ($settledAmount !== null) {
                DB::table('open_item_settlements')->insert([
                    'id' => sprintf('94000000-0000-4000-9000-%012d', $index + 1),
                    'administration_id' => self::ADMIN,
                    'open_item_id' => $sourceOpenItemId,
                    'payment_allocation_id' => null,
                    'effective_date' => '2026-08-25',
                    'amount' => $settledAmount,
                    'currency' => 'EUR',
                    'source_journal_entry_id' => $sourceJournalEntryId,
                    'type' => 'applied',
                    'reversed_settlement_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $sourceLineId = DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('id');
            $this->post('/purchasing/credits', [
                'source_invoice_id' => $invoice->id,
                'supplier_credit_invoice_number' => 'CR-'.$suffix,
                'supplier_credit_date' => '2026-08-23',
                'received_date' => '2026-08-24',
                'source_line_ids' => [$sourceLineId],
            ]);
            $credit = DB::table('purchase_credit_invoices')->where('supplier_credit_invoice_number', 'CR-'.$suffix)->first();
            self::assertNotNull($credit);
            $this->post('/purchasing/credits/'.$credit->id.'/finalize');
            $response = $this->post('/purchasing/credits/'.$credit->id.'/post', ['posting_date' => '2026-08-25']);

            $response->assertRedirect('/purchasing/credits/'.$credit->id)->assertSessionHas('status', $expectedMessage);
            if ($suffix === 'PAID') {
                $response->assertSessionMissing('status', 'Creditnota is geboekt en automatisch met de bronfactuur verrekend.');
            }
        }
    }

    private function fixtures(): void
    {
        $user = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($user, new DisplayName('Purchase Web'), new EmailAddress('purchase-web@example.com'), 'correct-secure-password');
        (new EloquentAdministrationRepository)->save(new Administration($this->admin(), new AdministrationCode('PWEB'), new AdministrationName('Purchase Web'), null, new Currency('EUR'), AdministrationStatus::Active));
        (new EloquentAdministrationMembershipRepository)->save(new AdministrationMembership(new AdministrationMembershipId(new Uuid(self::MEMBERSHIP)), $user, $this->admin(), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
        $this->app->make(PurchasingAuthorizationProvisioner::class)->provision();
        $now = now();
        DB::table('relations')->insert(['id' => self::RELATION, 'administration_id' => self::ADMIN, 'code' => 'SUP', 'display_name' => 'Supplier <script>alert(1)</script>', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('suppliers')->insert(['id' => self::SUPPLIER, 'administration_id' => self::ADMIN, 'relation_id' => self::RELATION, 'supplier_number' => 'S000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::EXPENSE, '4000', 'Expense', 'expense'], [self::AP, '1600', 'Accounts payable', 'liability'], [self::VAT, '1520', 'Input VAT', 'asset']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => self::JOURNAL, 'administration_id' => self::ADMIN, 'code' => 'INK', 'name' => 'Purchase', 'type' => 'purchase', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::TAX, 'INBTW21', 'Input 21', 'input'], [self::OUTPUT, 'OUTPUT', 'Output', 'output']] as [$id, $code, $name, $direction]) {
            DB::table('tax_codes')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'name' => $name, 'rate' => '21', 'direction' => $direction, 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('purchase_posting_configurations')->insert(['administration_id' => self::ADMIN, 'purchase_journal_id' => self::JOURNAL, 'accounts_payable_ledger_account_id' => self::AP, 'input_vat_ledger_account_id' => self::VAT, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function payload(string $number): array
    {
        return ['supplier_id' => self::SUPPLIER, 'supplier_invoice_number' => $number, 'invoice_date' => '2026-08-20', 'received_date' => '2026-08-22', 'due_date' => '2026-09-20', 'currency' => 'USD', 'address_line_1' => 'Document <script>alert(2)</script>', 'postal_code' => '1000AA', 'city' => 'Amsterdam', 'country_code' => 'NL', 'lines' => [['description' => 'Purchase', 'quantity' => '1', 'unit_price' => '100', 'ledger_account_id' => self::EXPENSE, 'tax_code_id' => self::TAX, 'fully_deductible' => '1'], ['description' => '', 'quantity' => '', 'unit_price' => '', 'ledger_account_id' => '', 'tax_code_id' => '', 'fully_deductible' => '1']]];
    }

    private function assignAll(): void
    {
        foreach (PurchasingPermission::cases() as $index => $permission) {
            $this->assignOnly($permission, $index + 20);
        }
    }

    private function assignOnly(PurchasingPermission $permission, int $sequence): void
    {
        $role = sprintf('94%06d-0000-4000-8000-000000000001', $sequence);
        DB::table('roles')->insert(['id' => $role, 'code' => 'PWEB'.$sequence, 'name' => 'Web role', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['id' => sprintf('94%06d-0000-4000-8000-000000000002', $sequence), 'role_id' => $role, 'permission_id' => $permission->id()->toString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('administration_membership_roles')->insert(['id' => sprintf('94%06d-0000-4000-8000-000000000003', $sequence), 'membership_id' => self::MEMBERSHIP, 'role_id' => $role, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'purchase-web@example.com', 'password' => 'correct-secure-password'])->assertRedirect();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN]);
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::ADMIN));
    }
}
