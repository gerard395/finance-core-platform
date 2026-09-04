<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use App\Application\Identity\ProvisionUserAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\BankingRole;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\BankingAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BankImportReconciliationWebTest extends TestCase
{
    use RefreshDatabase;

    private const string USER = 'b5000000-0000-4000-8000-000000000001';

    private const string ADMIN = 'b5000000-0000-4000-8000-000000000002';

    private const string OTHER_ADMIN = 'b5000000-0000-4000-8000-000000000003';

    private const string MEMBERSHIP = 'b5000000-0000-4000-8000-000000000004';

    private const string OTHER_MEMBERSHIP = 'b5000000-0000-4000-8000-000000000011';

    private const string BANK = 'b5000000-0000-4000-8000-000000000005';

    private const string BANK_LEDGER = 'b5000000-0000-4000-8000-000000000006';

    private const string CONTRA = 'b5000000-0000-4000-8000-000000000007';

    private const string JOURNAL = 'b5000000-0000-4000-8000-000000000008';

    private const string CUSTOMER = 'b5000000-0000-4000-8000-000000000012';

    private const string SUPPLIER = 'b5000000-0000-4000-8000-000000000013';

    private const string AR = 'b5000000-0000-4000-8000-000000000014';

    private const string AP = 'b5000000-0000-4000-8000-000000000015';

    private const string SOURCE_JOURNAL = 'b5000000-0000-4000-8000-000000000016';

    private const string RECEIVABLE_ONE = 'b5000000-0000-4000-8000-000000000017';

    private const string RECEIVABLE_TWO = 'b5000000-0000-4000-8000-000000000018';

    private const string PAYABLE = 'b5000000-0000-4000-8000-000000000019';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('bank_imports');
        $this->fixtures();
        $this->login();
    }

    public function test_upload_preview_confirm_is_server_owned_one_time_and_secure(): void
    {
        $this->assign(BankingRole::ImportUploader, 1);
        $preview = $this->post('/bank/import/preview', ['bank_account_id' => self::BANK, 'file' => UploadedFile::fake()->createWithContent('statement.xml', $this->xml())]);
        $preview->assertOk()->assertSee('CAMT-importpreview')->assertSee('STATEMENT-WEB')->assertSee('Import bevestigen')->assertDontSee('<script>alert(1)</script>', false);
        preg_match('/name="preview_token" value="([a-f0-9]{64})"/', $preview->getContent(), $matches);
        self::assertArrayHasKey(1, $matches);
        $token = $matches[1];
        self::assertSame(0, DB::table('bank_import_batches')->count());
        self::assertSame(3, DB::table('journal_entries')->count());

        $this->post('/bank/import/confirm', ['preview_token' => str_repeat('a', 64)])->assertRedirect('/bank/import')->assertSessionHas('error');
        $confirmed = $this->post('/bank/import/confirm', ['preview_token' => $token]);
        $batch = DB::table('bank_import_batches')->first();
        self::assertNotNull($batch);
        $confirmed->assertRedirect('/bank/import/batches/'.$batch->id);
        self::assertSame(1, DB::table('bank_statements')->count());
        self::assertSame(2, DB::table('bank_statement_entries')->count());
        self::assertSame(0, DB::table('bank_transactions')->count());
        $statement = DB::table('bank_statements')->value('id');
        $this->get('/bank/import/batches')->assertOk()->assertSee('Immutable bronfeiten');
        $this->get('/bank/import/batches/'.$batch->id)->assertOk()->assertSee('STATEMENT-WEB')->assertSee(substr($batch->original_file_hash, 0, 16));
        $this->get('/bank/import/statements/'.$statement)->assertOk()->assertSee('Opening')->assertSee('Closing')->assertSee('WEB-IN');
        $this->post('/bank/import/confirm', ['preview_token' => $token])->assertRedirect('/bank/import')->assertSessionHas('error');
        self::assertSame(1, DB::table('bank_import_batches')->count());

        $v02 = str_replace(['camt.053.001.08', 'STATEMENT-WEB', 'WEB-IN', 'WEB-OUT'], ['camt.053.001.02', 'STATEMENT-V02', 'V02-IN', 'V02-OUT'], $this->xml());
        $this->post('/bank/import/preview', ['bank_account_id' => self::BANK, 'file' => UploadedFile::fake()->createWithContent('v02.xml', $v02)])->assertOk()->assertSee('camt.053.001.02');
        $this->post('/bank/import/preview', ['bank_account_id' => self::BANK, 'file' => UploadedFile::fake()->createWithContent('duplicate.xml', $this->xml())])->assertRedirect()->assertSessionHasErrors('file', 'Dit bestand is al geïmporteerd.');
        $mismatch = str_replace('NL91ABNA0417164300', 'NL91ABNA0417164301', $this->xml());
        $this->post('/bank/import/preview', ['bank_account_id' => self::BANK, 'file' => UploadedFile::fake()->createWithContent('mismatch.xml', $mismatch)])->assertRedirect()->assertSessionHasErrors('file');
        $unbalanced = str_replace(['STATEMENT-WEB', 'WEB-IN', 'WEB-OUT', '<Amt Ccy="EUR">125</Amt>'], ['STATEMENT-UNBALANCED', 'UNBALANCED-IN', 'UNBALANCED-OUT', '<Amt Ccy="EUR">126</Amt>'], $this->xml());
        $this->post('/bank/import/preview', ['bank_account_id' => self::BANK, 'file' => UploadedFile::fake()->createWithContent('unbalanced.xml', $unbalanced)])->assertRedirect()->assertSessionHasErrors('file', 'Beginstand, mutaties en eindstand sluiten niet op elkaar aan.');

        $this->get('/bank/import/preview')->assertMethodNotAllowed();
        $this->get('/bank/import/confirm')->assertMethodNotAllowed();
        $this->post('/bank/import/preview', ['bank_account_id' => self::BANK, 'file' => UploadedFile::fake()->createWithContent('xxe.xml', '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><Document>&xxe;</Document>')])->assertRedirect()->assertSessionHasErrors('file');
    }

    public function test_worklist_ignore_restore_other_post_reversal_and_rereconciliation_are_complete(): void
    {
        $this->import();
        $this->assign(BankingRole::ImportPoster, 2);
        $this->assign(BankingRole::ReversalOperator, 3);
        $credit = DB::table('bank_statement_entries')->where('direction', 'CRDT')->first();
        $debit = DB::table('bank_statement_entries')->where('direction', 'DBIT')->first();
        self::assertNotNull($credit);
        self::assertNotNull($debit);

        $this->get('/bank/reconciliation')->assertOk()->assertSee('Counterparty &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/bank/reconciliation/'.$credit->id)->assertOk()->assertSee('Klantontvangst')->assertDontSee('Leveranciersbetaling')->assertSee('Overige transactie')->assertSee('4990 · Overige');
        $this->get('/bank/reconciliation/'.$debit->id)->assertOk()->assertSee('Leveranciersbetaling')->assertDontSee('Klantontvangst');

        $this->post('/bank/reconciliation/'.$debit->id.'/ignore', ['reason' => '<script>alert(2)</script> handmatig'])->assertRedirect();
        $this->get('/bank/reconciliation/'.$debit->id)->assertOk()->assertSee('&lt;script&gt;alert(2)&lt;/script&gt; handmatig', false)->assertDontSee('<script>alert(2)</script>', false);
        $this->post('/bank/reconciliation/'.$debit->id.'/restore', ['reason' => 'Opnieuw beoordelen'])->assertRedirect();
        self::assertSame(2, DB::table('bank_entry_reconciliation_history')->where('bank_statement_entry_id', $debit->id)->count());

        $posted = $this->post('/bank/reconciliation/'.$credit->id.'/post', ['intent' => 'other', 'posting_date' => '2026-09-03', 'contra_ledger_account_id' => self::CONTRA, 'description' => '<script>ignored authority</script>', 'administration_id' => self::OTHER_ADMIN]);
        $posted->assertRedirect('/bank/reconciliation/'.$credit->id)->assertSessionHas('status');
        self::assertSame(1, DB::table('bank_entry_reconciliations')->count());
        self::assertSame(1, DB::table('bank_entry_active_reconciliations')->count());
        self::assertSame(1, DB::table('bank_transactions')->count());
        self::assertSame(1, DB::table('journal_entries')->where('journal_id', self::JOURNAL)->count());
        self::assertSame(self::ADMIN, DB::table('bank_transactions')->value('administration_id'));
        $detail = $this->get('/bank/reconciliation/'.$credit->id)->assertOk()->assertSee('Gereconcilede financiële waarheid')->assertSee('Boeking corrigeren / terugdraaien');
        $detail->assertDontSee('<script>ignored authority</script>', false);

        $this->get('/bank/reconciliation/'.$credit->id.'/reverse')->assertOk()->assertSee('Geïmporteerde bankboeking corrigeren');
        $this->post('/bank/reconciliation/'.$credit->id.'/reverse', ['reversal_posting_date' => '2026-09-04', 'reason' => 'Handmatige correctie'])->assertRedirect('/bank/reconciliation/'.$credit->id);
        self::assertSame(0, DB::table('bank_entry_active_reconciliations')->count());
        self::assertSame(1, DB::table('bank_transaction_reversals')->count());
        $this->get('/bank/reconciliation/'.$credit->id)->assertOk()->assertSee('Teruggedraaide financiële waarheid')->assertSee('Opnieuw reconciliëren');
    }

    public function test_permission_matrix_revocation_inactive_membership_uuid_and_tenant_are_fail_closed(): void
    {
        $this->assign(BankingRole::ImportUploader, 11);
        $this->get('/bank/import')->assertOk();
        $this->get('/bank/reconciliation')->assertOk();
        $this->post('/bank/reconciliation/00000000-0000-4000-8000-000000000001/ignore', ['reason' => 'x'])->assertForbidden();
        $this->post('/bank/reconciliation/00000000-0000-4000-8000-000000000001/post', ['intent' => 'other', 'posting_date' => '2026-09-03', 'contra_ledger_account_id' => self::CONTRA])->assertForbidden();

        DB::table('administration_membership_roles')->delete();
        $this->assign(BankingRole::Reconciler, 12);
        $this->get('/bank/import')->assertForbidden();
        $this->get('/bank/reconciliation')->assertOk();
        $this->post('/bank/reconciliation/not-a-uuid/ignore', ['reason' => 'x'])->assertNotFound();
        $this->post('/bank/reconciliation/00000000-0000-4000-8000-000000000001/post', ['intent' => 'other', 'posting_date' => '2026-09-03', 'contra_ledger_account_id' => self::CONTRA])->assertForbidden();

        DB::table('administration_membership_roles')->delete();
        $this->assign(BankingRole::ImportPoster, 13);
        $this->post('/bank/reconciliation/not-a-uuid/post', ['intent' => 'other', 'posting_date' => '2026-09-03', 'contra_ledger_account_id' => self::CONTRA])->assertNotFound();
        $this->get('/bank/reconciliation/not-a-uuid')->assertNotFound();
        $this->get('/bank/reconciliation/00000000-0000-4000-8000-000000000001/reverse')->assertForbidden();

        DB::table('administration_membership_roles')->update(['active' => false]);
        $this->get('/bank/reconciliation')->assertForbidden();
        DB::table('administration_memberships')->where('id', self::MEMBERSHIP)->update(['active' => false]);
        $this->get('/bank/reconciliation')->assertRedirect('/administrations/select');
    }

    public function test_customer_receipt_supplier_payment_and_multiple_allocations_use_existing_application_contract(): void
    {
        $this->import();
        $this->assign(BankingRole::ImportPoster, 40);
        $credit = DB::table('bank_statement_entries')->where('direction', 'CRDT')->value('id');
        $debit = DB::table('bank_statement_entries')->where('direction', 'DBIT')->value('id');

        $this->post('/bank/reconciliation/'.$credit.'/post', [
            'intent' => 'customer_receipt', 'posting_date' => '2026-09-03', 'relation_id' => self::CUSTOMER,
            'allocations' => [['open_item_id' => self::RECEIVABLE_ONE, 'amount' => '20'], ['open_item_id' => self::RECEIVABLE_TWO, 'amount' => '30']],
        ])->assertRedirect('/bank/reconciliation/'.$credit)->assertSessionHas('status');
        $this->post('/bank/reconciliation/'.$debit.'/post', [
            'intent' => 'supplier_payment', 'posting_date' => '2026-09-03', 'relation_id' => self::SUPPLIER,
            'allocations' => [['open_item_id' => self::PAYABLE, 'amount' => '25']],
        ])->assertRedirect('/bank/reconciliation/'.$debit)->assertSessionHas('status');

        self::assertSame(2, DB::table('bank_transactions')->count());
        self::assertSame(2, DB::table('payments')->count());
        self::assertSame(3, DB::table('payment_allocations')->count());
        self::assertSame(3, DB::table('open_item_settlements')->count());
        self::assertSame(2, DB::table('bank_entry_active_reconciliations')->count());
        self::assertSame(0, DB::table('other_bank_transaction_intents')->count());
    }

    public function test_protected_contra_ap_denial_and_invalid_allocation_preserve_no_financial_facts(): void
    {
        $this->import();
        $this->assign(BankingRole::ImportPoster, 41);
        $credit = DB::table('bank_statement_entries')->where('direction', 'CRDT')->value('id');
        $protected = $this->post('/bank/reconciliation/'.$credit.'/post', ['intent' => 'other', 'posting_date' => '2026-09-03', 'contra_ledger_account_id' => self::BANK_LEDGER]);
        $protected->assertRedirect()->assertSessionHas('error', 'De geselecteerde tegenrekening is niet toegestaan.')->assertSessionHasInput('posting_date', '2026-09-03');
        self::assertSame(0, DB::table('bank_transactions')->count());

        $closed = $this->post('/bank/reconciliation/'.$credit.'/post', ['intent' => 'other', 'posting_date' => '2027-01-01', 'contra_ledger_account_id' => self::CONTRA]);
        $closed->assertRedirect()->assertSessionHas('error', 'Er is geen boekingsperiode voor de gekozen PostingDate ingericht.')->assertSessionHasInput('contra_ledger_account_id', self::CONTRA);
        self::assertSame(0, DB::table('journal_entries')->where('journal_id', self::JOURNAL)->count());

        $invalid = $this->post('/bank/reconciliation/'.$credit.'/post', ['intent' => 'customer_receipt', 'posting_date' => '2026-09-03', 'relation_id' => self::CUSTOMER, 'allocations' => [['open_item_id' => self::PAYABLE, 'amount' => '50']]]);
        $invalid->assertRedirect()->assertSessionHas('error');
        self::assertSame(0, DB::table('bank_entry_reconciliations')->count());
    }

    public function test_second_administration_cannot_read_first_administration_source_ids(): void
    {
        $this->import();
        $batch = DB::table('bank_import_batches')->value('id');
        $statement = DB::table('bank_statements')->value('id');
        $entry = DB::table('bank_statement_entries')->value('id');
        $this->assign(BankingRole::Reconciler, 42, self::OTHER_MEMBERSHIP);
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::OTHER_ADMIN]);

        $this->get('/bank/import/batches/'.$batch)->assertNotFound();
        $this->get('/bank/import/statements/'.$statement)->assertNotFound();
        $this->get('/bank/reconciliation/'.$entry)->assertNotFound();
        $this->post('/bank/reconciliation/'.$entry.'/ignore', ['reason' => 'tenant spoof'])->assertNotFound();
        self::assertSame(0, DB::table('bank_entry_reconciliation_history')->count());
    }

    public function test_mutations_are_post_only_and_render_csrf_tokens(): void
    {
        $this->assign(BankingRole::ImportUploader, 43);
        $this->get('/bank/import')->assertOk()->assertSee('name="_token"', false);
        self::assertSame(['POST'], app('router')->getRoutes()->getByName('banking.import.confirm')->methods());
        self::assertSame(['POST'], app('router')->getRoutes()->getByName('banking.reconciliation.post')->methods());
        self::assertContains('web', app('router')->getRoutes()->getByName('banking.import.confirm')->gatherMiddleware());
    }

    public function test_validation_roundtrip_is_allowlisted_and_invalid_methods_do_not_mutate(): void
    {
        $this->import();
        $this->assign(BankingRole::ImportPoster, 21);
        $entry = DB::table('bank_statement_entries')->where('direction', 'CRDT')->value('id');
        $response = $this->from('/bank/reconciliation/'.$entry)->post('/bank/reconciliation/'.$entry.'/post', ['intent' => 'other', 'posting_date' => '2026-09-03', 'contra_ledger_account_id' => 'not-a-uuid', 'administration_id' => self::OTHER_ADMIN, 'definition_id' => 'client-authority']);
        $response->assertRedirect('/bank/reconciliation/'.$entry)->assertSessionHasErrors('contra_ledger_account_id')->assertSessionHasInput('posting_date', '2026-09-03')->assertSessionMissing('_old_input.administration_id')->assertSessionMissing('_old_input.definition_id');
        self::assertSame(0, DB::table('bank_transactions')->count());
        $this->get('/bank/reconciliation/'.$entry.'/ignore')->assertMethodNotAllowed();
        $this->get('/bank/reconciliation/'.$entry.'/restore')->assertMethodNotAllowed();
        $this->get('/bank/reconciliation/'.$entry.'/post')->assertMethodNotAllowed();
    }

    private function import(): void
    {
        $this->assign(BankingRole::ImportUploader, 30);
        $preview = $this->post('/bank/import/preview', ['bank_account_id' => self::BANK, 'file' => UploadedFile::fake()->createWithContent('statement.xml', $this->xml())])->assertOk();
        preg_match('/name="preview_token" value="([a-f0-9]{64})"/', $preview->getContent(), $matches);
        $this->post('/bank/import/confirm', ['preview_token' => $matches[1]])->assertRedirect();
    }

    private function fixtures(): void
    {
        $user = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($user, new DisplayName('BIR Web'), new EmailAddress('bir-web@example.test'), 'correct-secure-password');
        (new EloquentAdministrationRepository)->save(new Administration(new AdministrationId(new Uuid(self::ADMIN)), new AdministrationCode('BIRWEB'), new AdministrationName('BIR Web'), null, new Currency('EUR'), AdministrationStatus::Active));
        (new EloquentAdministrationRepository)->save(new Administration(new AdministrationId(new Uuid(self::OTHER_ADMIN)), new AdministrationCode('BIRWB'), new AdministrationName('Other'), null, new Currency('EUR'), AdministrationStatus::Active));
        (new EloquentAdministrationMembershipRepository)->save(new AdministrationMembership(new AdministrationMembershipId(new Uuid(self::MEMBERSHIP)), $user, new AdministrationId(new Uuid(self::ADMIN)), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
        (new EloquentAdministrationMembershipRepository)->save(new AdministrationMembership(new AdministrationMembershipId(new Uuid(self::OTHER_MEMBERSHIP)), $user, new AdministrationId(new Uuid(self::OTHER_ADMIN)), true, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01')));
        $this->app->make(BankingAuthorizationProvisioner::class)->provision();
        $now = now();
        DB::table('administration_bank_accounts')->insert(['id' => self::BANK, 'administration_id' => self::ADMIN, 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_holder' => 'BIR Web', 'label' => 'Hoofdrekening', 'currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::BANK_LEDGER, '1100', 'Bank', 'asset'], [self::CONTRA, '4990', 'Overige', 'expense'], [self::AR, '1300', 'Debiteuren', 'asset'], [self::AP, '1400', 'Crediteuren', 'liability']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => self::JOURNAL, 'administration_id' => self::ADMIN, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'bank', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journals')->insert(['id' => self::SOURCE_JOURNAL, 'administration_id' => self::ADMIN, 'code' => 'OPEN', 'name' => 'Opening', 'type' => 'general', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('banking_posting_configurations')->insert(['administration_id' => self::ADMIN, 'administration_bank_account_id' => self::BANK, 'bank_journal_id' => self::JOURNAL, 'bank_ledger_account_id' => self::BANK_LEDGER, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('book_years')->insert(['id' => 'b5000000-0000-4000-8000-000000000009', 'administration_id' => self::ADMIN, 'code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('accounting_periods')->insert(['id' => 'b5000000-0000-4000-8000-000000000010', 'administration_id' => self::ADMIN, 'book_year_id' => 'b5000000-0000-4000-8000-000000000009', 'code' => '2026', 'label' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert([['id' => self::CUSTOMER, 'administration_id' => self::ADMIN, 'code' => 'CUS', 'display_name' => 'Klant', 'active' => true, 'created_at' => $now, 'updated_at' => $now], ['id' => self::SUPPLIER, 'administration_id' => self::ADMIN, 'code' => 'SUP', 'display_name' => 'Leverancier', 'active' => true, 'created_at' => $now, 'updated_at' => $now]]);
        foreach ([[20, 'OPEN-C1'], [21, 'OPEN-C2'], [22, 'OPEN-S1']] as [$number, $reference]) {
            DB::table('journal_entries')->insert(['id' => sprintf('b5000000-0000-4000-8000-%012d', $number), 'administration_id' => self::ADMIN, 'journal_id' => self::SOURCE_JOURNAL, 'posting_date' => '2026-09-01', 'reference' => $reference, 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('open_items')->insert([
            ['id' => self::RECEIVABLE_ONE, 'administration_id' => self::ADMIN, 'relation_id' => self::CUSTOMER, 'journal_entry_id' => 'b5000000-0000-4000-8000-000000000020', 'control_ledger_account_id' => self::AR, 'open_item_type' => 'receivable', 'side' => 'debit', 'original_amount' => '20', 'currency' => 'EUR', 'opened_on' => '2026-09-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::RECEIVABLE_TWO, 'administration_id' => self::ADMIN, 'relation_id' => self::CUSTOMER, 'journal_entry_id' => 'b5000000-0000-4000-8000-000000000021', 'control_ledger_account_id' => self::AR, 'open_item_type' => 'receivable', 'side' => 'debit', 'original_amount' => '30', 'currency' => 'EUR', 'opened_on' => '2026-09-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::PAYABLE, 'administration_id' => self::ADMIN, 'relation_id' => self::SUPPLIER, 'journal_entry_id' => 'b5000000-0000-4000-8000-000000000022', 'control_ledger_account_id' => self::AP, 'open_item_type' => 'payable', 'side' => 'credit', 'original_amount' => '25', 'currency' => 'EUR', 'opened_on' => '2026-09-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function assign(BankingRole $role, int $sequence, string $membership = self::MEMBERSHIP): void
    {
        DB::table('administration_membership_roles')->insertOrIgnore(['id' => sprintf('b51%05d-0000-4000-8000-000000000001', $sequence), 'membership_id' => $membership, 'role_id' => $role->id()->toString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'bir-web@example.test', 'password' => 'correct-secure-password'])->assertRedirect();
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::ADMIN]);
    }

    private function xml(): string
    {
        return '<?xml version="1.0"?><Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08"><BkToCstmrStmt><Stmt><Id>STATEMENT-WEB</Id><Acct><Id><IBAN>NL91ABNA0417164300</IBAN></Id><Ccy>EUR</Ccy></Acct><Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">100</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal><Bal><Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">125</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal><Ntry><Amt Ccy="EUR">50</Amt><CdtDbtInd>CRDT</CdtDbtInd><BookgDt><Dt>2026-09-03</Dt></BookgDt><AcctSvcrRef>WEB-IN</AcctSvcrRef><NtryDtls><TxDtls><RltdPties><Dbtr><Nm>Counterparty &lt;script&gt;alert(1)&lt;/script&gt;</Nm></Dbtr></RltdPties><RmtInf><Ustrd>Ontvangst</Ustrd></RmtInf></TxDtls></NtryDtls></Ntry><Ntry><Amt Ccy="EUR">25</Amt><CdtDbtInd>DBIT</CdtDbtInd><BookgDt><Dt>2026-09-03</Dt></BookgDt><AcctSvcrRef>WEB-OUT</AcctSvcrRef></Ntry></Stmt></BkToCstmrStmt></Document>';
    }
}
