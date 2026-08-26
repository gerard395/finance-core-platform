<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\AdministrationPermission;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\AdministrationAuthorizationProvisioner;
use App\Infrastructure\Identity\BankingAuthorizationProvisioner;
use App\Infrastructure\Identity\PurchasingAuthorizationProvisioner;
use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BankPaymentWebTest extends TestCase
{
    use RefreshDatabase;

    private const USER = 'b4000000-0000-4000-8000-000000000001';

    private const ADMIN = 'b4000000-0000-4000-8000-000000000002';

    private const OTHER_ADMIN = 'b4000000-0000-4000-8000-000000000003';

    private const MEMBERSHIP = 'b4000000-0000-4000-8000-000000000004';

    private const CUSTOMER = 'b4000000-0000-4000-8000-000000000005';

    private const SUPPLIER = 'b4000000-0000-4000-8000-000000000006';

    private const BANK = 'b4000000-0000-4000-8000-000000000007';

    private const BANK_LEDGER = 'b4000000-0000-4000-8000-000000000008';

    private const AR = 'b4000000-0000-4000-8000-000000000009';

    private const AP = 'b4000000-0000-4000-8000-000000000010';

    private const BANK_JOURNAL = 'b4000000-0000-4000-8000-000000000011';

    private const SOURCE_JOURNAL = 'b4000000-0000-4000-8000-000000000012';

    private const RECEIVABLE = 'b4000000-0000-4000-8000-000000000013';

    private const PAYABLE = 'b4000000-0000-4000-8000-000000000014';

    private const RECEIVABLE_ENTRY = 'b4000000-0000-4000-8000-000000000015';

    private const PAYABLE_ENTRY = 'b4000000-0000-4000-8000-000000000016';

    private const RECEIVABLE_TWO = 'b4000000-0000-4000-8000-000000000017';

    private const RECEIVABLE_THREE = 'b4000000-0000-4000-8000-000000000018';

    private const RECEIVABLE_TWO_ENTRY = 'b4000000-0000-4000-8000-000000000019';

    private const RECEIVABLE_THREE_ENTRY = 'b4000000-0000-4000-8000-000000000020';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
    }

    public function test_customer_and_supplier_web_flows_are_tenant_safe_escaped_and_idempotent(): void
    {
        $this->assignAll();
        $this->login();
        $this->get('/banking/payments')->assertOk()->assertSee('Nieuwe betaling')->assertSee('Betalingen');
        $this->get('/banking/payments/create')->assertOk()->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Klantontvangst')->assertSee('Leveranciersbetaling')->assertSee('NL91ABNA0417164300');

        $payload = $this->payload('customer_receipt', self::CUSTOMER, self::RECEIVABLE, '40', 'RECEIPT');
        $payload['administration_id'] = self::OTHER_ADMIN;
        $this->post('/banking/payments', $payload);
        $receipt = DB::table('bank_transactions')->where('reference', 'RECEIPT')->first();
        self::assertNotNull($receipt);
        self::assertSame(self::ADMIN, $receipt->administration_id);
        self::assertSame('40.00000000', $receipt->amount);
        $this->get('/banking/payments/'.$receipt->id)->assertOk()->assertSee('Customer &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/banking/payments/'.$receipt->id.'/edit')->assertOk();
        $payload['description'] = 'Bijgewerkt';
        $allocation = DB::table('payment_allocations')->where('payment_id', DB::table('payments')->where('bank_transaction_id', $receipt->id)->value('id'))->first();
        $payload['allocations'][0]['allocation_id'] = $allocation->id;
        $this->put('/banking/payments/'.$receipt->id, $payload)->assertRedirect('/banking/payments/'.$receipt->id);
        self::assertSame('Bijgewerkt', DB::table('bank_transactions')->where('id', $receipt->id)->value('description'));
        $this->post('/banking/payments/'.$receipt->id.'/finalize')->assertRedirect('/banking/payments/'.$receipt->id);
        $this->post('/banking/payments/'.$receipt->id.'/post', ['posting_date' => '2026-08-26'])->assertRedirect('/banking/payments/'.$receipt->id);
        $this->post('/banking/payments/'.$receipt->id.'/post', ['posting_date' => '2026-08-26'])->assertRedirect('/banking/payments/'.$receipt->id);
        self::assertSame(1, DB::table('bank_transaction_postings')->where('bank_transaction_id', $receipt->id)->count());
        self::assertSame(1, DB::table('open_item_settlements')->where('open_item_id', self::RECEIVABLE)->count());
        self::assertSame(1, DB::table('journal_entries')->where('reference', 'RECEIPT')->count());
        $this->get('/banking/payments/'.$receipt->id)->assertOk()->assertSee('JournalEntry')->assertSee('EUR 60,00')->assertDontSee('Bewerken')->assertDontSee('Annuleren')->assertDontSee('Finaliseren');

        $this->post('/banking/payments', $this->payload('customer_receipt', self::CUSTOMER, self::RECEIVABLE, '60', 'RECEIPT-2'));
        $receiptTwo = DB::table('bank_transactions')->where('reference', 'RECEIPT-2')->value('id');
        $this->post('/banking/payments/'.$receiptTwo.'/finalize')->assertRedirect();
        $this->post('/banking/payments/'.$receiptTwo.'/post', ['posting_date' => '2026-08-26'])->assertRedirect();
        self::assertSame(2, DB::table('open_item_settlements')->where('open_item_id', self::RECEIVABLE)->count());
        self::assertSame(0, bccomp('100', (string) DB::table('open_item_settlements')->where('open_item_id', self::RECEIVABLE)->sum('amount'), 4));

        $multi = $this->payload('customer_receipt', self::CUSTOMER, self::RECEIVABLE_TWO, '50', 'MULTI');
        $multi['allocations'] = [['open_item_id' => self::RECEIVABLE_TWO, 'amount' => '30'], ['open_item_id' => self::RECEIVABLE_THREE, 'amount' => '20']];
        $this->post('/banking/payments', $multi);
        $multiId = DB::table('bank_transactions')->where('reference', 'MULTI')->value('id');
        $this->post('/banking/payments/'.$multiId.'/finalize')->assertRedirect();
        $this->post('/banking/payments/'.$multiId.'/post', ['posting_date' => '2026-08-26'])->assertRedirect();
        self::assertSame(1, DB::table('bank_transaction_postings')->where('bank_transaction_id', $multiId)->count());
        self::assertSame(2, DB::table('open_item_settlements')->whereIn('open_item_id', [self::RECEIVABLE_TWO, self::RECEIVABLE_THREE])->count());

        $this->post('/banking/payments', $this->payload('supplier_payment', self::SUPPLIER, self::PAYABLE, '40', 'SUPPLIER'));
        $supplier = DB::table('bank_transactions')->where('reference', 'SUPPLIER')->first();
        self::assertNotNull($supplier);
        self::assertSame('-40.00000000', $supplier->amount);
        $this->post('/banking/payments/'.$supplier->id.'/finalize')->assertRedirect();
        $this->post('/banking/payments/'.$supplier->id.'/post', ['posting_date' => '2026-08-26'])->assertRedirect();
        self::assertSame(1, DB::table('open_item_settlements')->where('open_item_id', self::PAYABLE)->count());
        $supplierEntry = DB::table('bank_transaction_postings')->where('bank_transaction_id', $supplier->id)->value('journal_entry_id');
        self::assertSame(0, bccomp('40', (string) DB::table('journal_entry_lines')->where('journal_entry_id', $supplierEntry)->where('ledger_account_id', self::AP)->value('debit_amount'), 4));
        self::assertSame(0, bccomp('40', (string) DB::table('journal_entry_lines')->where('journal_entry_id', $supplierEntry)->where('ledger_account_id', self::BANK_LEDGER)->value('credit_amount'), 4));

        $this->post('/banking/payments', $this->payload('supplier_payment', self::SUPPLIER, self::PAYABLE, '10', 'CANCEL'));
        $cancelled = DB::table('bank_transactions')->where('reference', 'CANCEL')->value('id');
        $this->post('/banking/payments/'.$cancelled.'/cancel')->assertRedirect();
        self::assertSame('cancelled', DB::table('bank_transactions')->where('id', $cancelled)->value('status'));
        $this->get('/banking/payments/not-a-uuid')->assertNotFound();
        $this->post('/banking/payments/not-a-uuid/post', ['posting_date' => '2026-08-26'])->assertNotFound();
    }

    public function test_view_manage_and_post_are_independent_and_revocation_is_immediate(): void
    {
        $this->login();
        foreach (BankingPermission::cases() as $index => $permission) {
            DB::table('administration_membership_roles')->delete();
            $this->assignOnly($permission, $index + 1);
            ($permission === BankingPermission::View ? $this->get('/banking/payments') : $this->get('/banking/payments'))->assertStatus($permission === BankingPermission::View ? 200 : 403);
            $this->get('/banking/payments/create')->assertStatus($permission === BankingPermission::ManagePayments ? 200 : 403);
            $this->post('/banking/payments/00000000-0000-4000-8000-000000000001/post', ['posting_date' => '2026-08-26'])->assertStatus($permission === BankingPermission::PostPayments ? 404 : 403);
        }
        DB::table('administration_membership_roles')->update(['active' => false]);
        $this->post('/banking/payments/00000000-0000-4000-8000-000000000001/post', ['posting_date' => '2026-08-26'])->assertForbidden();
        DB::table('administration_memberships')->where('id', self::MEMBERSHIP)->update(['active' => false]);
        $this->get('/banking/payments')->assertRedirect('/administrations/select');
    }

    public function test_missing_configuration_is_safe_and_links_authorized_settings_user(): void
    {
        $this->assignAll();
        $this->assignAdministrationSettings();
        $this->login();
        DB::table('banking_posting_configurations')->delete();
        $this->post('/banking/payments', $this->payload('customer_receipt', self::CUSTOMER, self::RECEIVABLE, '10', 'NO-CONFIG'));
        $id = DB::table('bank_transactions')->where('reference', 'NO-CONFIG')->value('id');
        $this->post('/banking/payments/'.$id.'/finalize')->assertRedirect();
        $this->followingRedirects()->post('/banking/payments/'.$id.'/post', ['posting_date' => '2026-08-26'])->assertOk()->assertSee('De bankboekingsinstellingen voor deze bankrekening zijn nog niet ingericht.')->assertSee('Naar Beheer → Instellingen');
        self::assertSame('finalized', DB::table('bank_transactions')->where('id', $id)->value('status'));
        self::assertSame(0, DB::table('bank_transaction_postings')->where('bank_transaction_id', $id)->count());
    }

    public function test_allocation_form_state_and_draft_payload_regressions(): void
    {
        $this->assignAll();
        $this->login();

        $create = $this->get('/banking/payments/create')->assertOk();
        $create->assertSee('data-allocation-amount', false)
            ->assertSee('disabled', false)
            ->assertSee('Selecteer een openstaande post en vul daarna het allocatiebedrag in.');

        $zero = $this->payload('supplier_payment', self::SUPPLIER, self::PAYABLE, '605', 'ZERO');
        $zero['allocations'] = [
            ['allocation_id' => '', 'amount' => ''],
            ['allocation_id' => '', 'amount' => ''],
        ];
        $this->post('/banking/payments', $zero)->assertRedirect();
        $zeroId = DB::table('bank_transactions')->where('reference', 'ZERO')->value('id');
        self::assertNotNull($zeroId);
        self::assertSame(0, DB::table('payment_allocations')->where('payment_id', DB::table('payments')->where('bank_transaction_id', $zeroId)->value('id'))->count());

        $empty = $this->payload('supplier_payment', self::SUPPLIER, self::PAYABLE, '605', 'EMPTY');
        $empty['allocations'][0]['amount'] = '';
        $this->from('/banking/payments/create')->post('/banking/payments', $empty)
            ->assertRedirect('/banking/payments/create')
            ->assertSessionHasErrors(['allocations.0.amount' => 'Vul een allocatiebedrag in voor iedere geselecteerde openstaande post.']);
        self::assertSame(0, DB::table('bank_transactions')->where('reference', 'EMPTY')->count());

        $full = $this->payload('supplier_payment', self::SUPPLIER, self::PAYABLE, '605', 'FULL-605');
        $full['allocations'][] = ['allocation_id' => '', 'amount' => ''];
        $this->post('/banking/payments', $full)->assertRedirect();
        $fullId = DB::table('bank_transactions')->where('reference', 'FULL-605')->value('id');
        self::assertSame('605.00000000', DB::table('payment_allocations')->where('payment_id', DB::table('payments')->where('bank_transaction_id', $fullId)->value('id'))->value('amount'));

        $partial = $this->payload('supplier_payment', self::SUPPLIER, self::PAYABLE, '605', 'PARTIAL-500');
        $partial['allocations'][0]['amount'] = '500';
        $this->post('/banking/payments', $partial)->assertRedirect();
        $partialId = DB::table('bank_transactions')->where('reference', 'PARTIAL-500')->value('id');
        self::assertSame('500.00000000', DB::table('payment_allocations')->where('payment_id', DB::table('payments')->where('bank_transaction_id', $partialId)->value('id'))->value('amount'));
        $this->post('/banking/payments/'.$partialId.'/finalize')->assertRedirect();
        self::assertSame('draft', DB::table('bank_transactions')->where('id', $partialId)->value('status'));

        $existing = $this->payload('supplier_payment', self::SUPPLIER, self::PAYABLE, '40', 'EXISTING');
        $this->post('/banking/payments', $existing)->assertRedirect();
        $existingId = DB::table('bank_transactions')->where('reference', 'EXISTING')->value('id');
        $edit = $this->get('/banking/payments/'.$existingId.'/edit')->assertOk();
        $edit->assertSee('checked', false)->assertSee('required', false)->assertSee('value="40"', false);
        $allocationId = DB::table('payment_allocations')->where('payment_id', DB::table('payments')->where('bank_transaction_id', $existingId)->value('id'))->value('id');
        $existing['allocations'][0] = ['allocation_id' => $allocationId, 'open_item_id' => self::PAYABLE, 'amount' => '30'];
        $this->put('/banking/payments/'.$existingId, $existing)->assertRedirect();
        self::assertSame('30.00000000', DB::table('payment_allocations')->where('id', $allocationId)->value('amount'));
        $existing['allocations'] = [
            ['allocation_id' => $allocationId, 'amount' => ''],
            ['allocation_id' => '', 'amount' => ''],
        ];
        $this->put('/banking/payments/'.$existingId, $existing)->assertRedirect();
        self::assertSame(0, DB::table('payment_allocations')->where('payment_id', DB::table('payments')->where('bank_transaction_id', $existingId)->value('id'))->count());
    }

    public function test_restored_form_state_uses_one_filter_path_for_change_and_pageshow(): void
    {
        $this->assignAll();
        $this->login();

        $fresh = $this->get('/banking/payments/create')->assertOk();
        $fresh->assertSee('data-type="customer_receipt" data-relation="'.self::CUSTOMER.'"', false)
            ->assertSee('data-type="supplier_payment" data-relation="'.self::SUPPLIER.'"', false)
            ->assertSee("type.addEventListener('change',update)", false)
            ->assertSee("relation.addEventListener('change',update)", false)
            ->assertSee("window.addEventListener('pageshow',update)", false)
            ->assertSee("document.querySelector('form').addEventListener('input'", false)
            ->assertSee('allocation.disabled=!checkbox.checked', false)
            ->assertSee('allocation.required=checkbox.checked', false)
            ->assertSee('if(!visible)checkbox.checked=false', false)
            ->assertSee('update()});', false);

        $restoredSupplier = $this->withSession(['_old_input' => ['payment_type' => 'supplier_payment', 'relation_id' => self::SUPPLIER]])
            ->get('/banking/payments/create')->assertOk();
        $restoredSupplier->assertSee('value="supplier_payment" selected', false)
            ->assertSee('value="'.self::SUPPLIER.'" selected', false)
            ->assertSee('data-type="supplier_payment" data-relation="'.self::SUPPLIER.'"', false);

        $restoredCustomer = $this->withSession(['_old_input' => ['payment_type' => 'customer_receipt', 'relation_id' => self::CUSTOMER]])
            ->get('/banking/payments/create')->assertOk();
        $restoredCustomer->assertSee('value="customer_receipt" selected', false)
            ->assertSee('value="'.self::CUSTOMER.'" selected', false)
            ->assertSee('data-type="customer_receipt" data-relation="'.self::CUSTOMER.'"', false);
    }

    public function test_settings_sales_and_purchasing_permissions_grant_no_banking_access(): void
    {
        $this->app->make(AdministrationAuthorizationProvisioner::class)->provision();
        $this->app->make(SalesAuthorizationProvisioner::class)->provision();
        $this->app->make(PurchasingAuthorizationProvisioner::class)->provision();
        $this->assignForeignPermission(AdministrationPermission::UpdateSettings->id()->toString(), 1);
        $this->assignForeignPermission(SalesPermission::View->id()->toString(), 2);
        $this->assignForeignPermission(PurchasingPermission::View->id()->toString(), 3);
        $this->login();

        $this->get('/banking/payments')->assertForbidden();
        $this->get('/banking/payments/create')->assertForbidden();
        $this->post('/banking/payments/00000000-0000-4000-8000-000000000001/post', ['posting_date' => '2026-08-26'])->assertForbidden();
    }

    private function fixtures(): void
    {
        $user = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($user, new DisplayName('Bank Web'), new EmailAddress('bank-web@example.com'), 'correct-secure-password');
        (new EloquentAdministrationRepository)->save(new Administration($this->admin(), new AdministrationCode('BWEB'), new AdministrationName('Bank Web'), null, new Currency('EUR'), AdministrationStatus::Active));
        (new EloquentAdministrationMembershipRepository)->save(new AdministrationMembership(new AdministrationMembershipId(new Uuid(self::MEMBERSHIP)), $user, $this->admin(), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
        $this->app->make(BankingAuthorizationProvisioner::class)->provision();
        $now = now();
        foreach ([[self::CUSTOMER, 'CUS', 'Customer <script>alert(1)</script>'], [self::SUPPLIER, 'SUP', 'Supplier']] as [$id, $code, $name]) {
            DB::table('relations')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'display_name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([[self::BANK_LEDGER, '1100', 'Bank', 'asset'], [self::AR, '1300', 'Receivable', 'asset'], [self::AP, '1400', 'Payable', 'liability']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([[self::BANK_JOURNAL, 'BANK', 'Bank', 'bank'], [self::SOURCE_JOURNAL, 'OPEN', 'Opening', 'general']] as [$id, $code, $name, $type]) {
            DB::table('journals')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('administration_bank_accounts')->insert(['id' => self::BANK, 'administration_id' => self::ADMIN, 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_holder' => 'Bank Web', 'label' => 'Hoofdrekening', 'currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('banking_posting_configurations')->insert(['administration_id' => self::ADMIN, 'administration_bank_account_id' => self::BANK, 'bank_journal_id' => self::BANK_JOURNAL, 'bank_ledger_account_id' => self::BANK_LEDGER, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::RECEIVABLE_ENTRY, 'INV-1'], [self::PAYABLE_ENTRY, 'PINV-1'], [self::RECEIVABLE_TWO_ENTRY, 'INV-2'], [self::RECEIVABLE_THREE_ENTRY, 'INV-3']] as [$id, $reference]) {
            DB::table('journal_entries')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'journal_id' => self::SOURCE_JOURNAL, 'posting_date' => '2026-08-01', 'reference' => $reference, 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('open_items')->insert([
            ['id' => self::RECEIVABLE, 'administration_id' => self::ADMIN, 'relation_id' => self::CUSTOMER, 'journal_entry_id' => self::RECEIVABLE_ENTRY, 'control_ledger_account_id' => self::AR, 'open_item_type' => 'receivable', 'side' => 'debit', 'original_amount' => '100', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => '2026-09-01', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::PAYABLE, 'administration_id' => self::ADMIN, 'relation_id' => self::SUPPLIER, 'journal_entry_id' => self::PAYABLE_ENTRY, 'control_ledger_account_id' => self::AP, 'open_item_type' => 'payable', 'side' => 'credit', 'original_amount' => '80', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => '2026-09-01', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::RECEIVABLE_TWO, 'administration_id' => self::ADMIN, 'relation_id' => self::CUSTOMER, 'journal_entry_id' => self::RECEIVABLE_TWO_ENTRY, 'control_ledger_account_id' => self::AR, 'open_item_type' => 'receivable', 'side' => 'debit', 'original_amount' => '30', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::RECEIVABLE_THREE, 'administration_id' => self::ADMIN, 'relation_id' => self::CUSTOMER, 'journal_entry_id' => self::RECEIVABLE_THREE_ENTRY, 'control_ledger_account_id' => self::AR, 'open_item_type' => 'receivable', 'side' => 'debit', 'original_amount' => '20', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function payload(string $type, string $relation, string $item, string $amount, string $reference): array
    {
        return ['bank_account_id' => self::BANK, 'transaction_date' => '2026-08-26', 'payment_type' => $type, 'amount' => $amount, 'relation_id' => $relation, 'reference' => $reference, 'description' => 'Web payment', 'allocations' => [['open_item_id' => $item, 'amount' => $amount]]];
    }

    private function assignAll(): void
    {
        foreach (BankingPermission::cases() as $index => $permission) {
            $this->assignOnly($permission, $index + 20);
        }
    }

    private function assignOnly(BankingPermission $permission, int $sequence): void
    {
        $role = sprintf('b41%05d-0000-4000-8000-000000000001', $sequence);
        DB::table('roles')->insert(['id' => $role, 'code' => 'BWEB'.$sequence, 'name' => 'Bank Web role', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['id' => sprintf('b41%05d-0000-4000-8000-000000000002', $sequence), 'role_id' => $role, 'permission_id' => $permission->id()->toString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('administration_membership_roles')->insert(['id' => sprintf('b41%05d-0000-4000-8000-000000000003', $sequence), 'membership_id' => self::MEMBERSHIP, 'role_id' => $role, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assignAdministrationSettings(): void
    {
        $this->app->make(AdministrationAuthorizationProvisioner::class)->provision();
        $permission = AdministrationPermission::UpdateSettings;
        $role = 'b4200000-0000-4000-8000-000000000001';
        DB::table('roles')->insert(['id' => $role, 'code' => 'BWEBSET', 'name' => 'Settings', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['id' => 'b4200000-0000-4000-8000-000000000002', 'role_id' => $role, 'permission_id' => $permission->id()->toString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('administration_membership_roles')->insert(['id' => 'b4200000-0000-4000-8000-000000000003', 'membership_id' => self::MEMBERSHIP, 'role_id' => $role, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assignForeignPermission(string $permissionId, int $sequence): void
    {
        $role = sprintf('b43%05d-0000-4000-8000-000000000001', $sequence);
        DB::table('roles')->insert(['id' => $role, 'code' => 'BFOREIGN'.$sequence, 'name' => 'Foreign permission', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['id' => sprintf('b43%05d-0000-4000-8000-000000000002', $sequence), 'role_id' => $role, 'permission_id' => $permissionId, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('administration_membership_roles')->insert(['id' => sprintf('b43%05d-0000-4000-8000-000000000003', $sequence), 'membership_id' => self::MEMBERSHIP, 'role_id' => $role, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'bank-web@example.com', 'password' => 'correct-secure-password'])->assertRedirect();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN]);
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::ADMIN));
    }
}
