<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Identity\ProvisionUserAccount;
use App\Application\Sales\PostSalesInvoice;
use App\Application\Sales\PostSalesInvoiceStatus;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Definitions\AdministrationRole;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\Entities\MembershipRole;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\MembershipRoleId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Middleware\EnsureActiveAdministration;
use App\Infrastructure\Identity\AdministrationAuthorizationProvisioner;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentLedgerAccountRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SalesPostingConfigurationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const string USER = '71000000-0000-4000-8000-000000000001';

    private const string A = '71000000-0000-4000-8000-000000000002';

    private const string B = '72000000-0000-4000-8000-000000000002';

    private const string MEMBERSHIP = '71000000-0000-4000-8000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity();
        $this->createOpenAccountingPeriodFixture(self::A);
        $this->createOpenAccountingPeriodFixture(self::B);
        $this->login();
    }

    public function test_empty_masterdata_is_explained_without_a_creation_or_development_shortcut(): void
    {
        $this->get('/settings/administration')->assertOk()
            ->assertSee('Verkoopboekingen')
            ->assertSee('Niet ingesteld')
            ->assertSee('Er zijn nog geen geschikte dagboeken/grootboekrekeningen beschikbaar.')
            ->assertDontSee('development:provision-demo-accounting')
            ->assertSee('disabled', false);
    }

    public function test_purchase_setup_section_provisions_only_same_tenant_domestic_input_catalogue(): void
    {
        $this->get('/settings/administration')->assertOk()
            ->assertSee('Inkoopboekingen')
            ->assertSee('De binnenlandse voorbelastingcatalogus ontbreekt nog.')
            ->assertSee('Maak eerst via Grootboekbeheer een actief inkoopdagboek');

        $this->post('/settings/administration/purchase-tax-codes', ['administration_id' => self::B])
            ->assertRedirect(route('settings.administration.edit'))
            ->assertSessionHas('status', 'Binnenlandse voorbelastingcodes beschikbaar gemaakt.');

        self::assertSame(5, DB::table('tax_codes')->where('administration_id', self::A)->where('direction', 'input')->count());
        self::assertSame(0, DB::table('tax_codes')->where('administration_id', self::B)->where('direction', 'input')->count());
        self::assertSame(0, DB::table('tax_codes')->where('direction', 'output')->count());
        $this->get('/settings/administration')->assertOk()->assertSee('5 binnenlandse voorbelastingcodes beschikbaar.');
    }

    public function test_purchase_setup_action_observes_runtime_settings_revocation(): void
    {
        DB::table('administration_membership_roles')->where('membership_id', self::MEMBERSHIP)->update(['active' => false]);

        $this->post('/settings/administration/purchase-tax-codes')->assertForbidden();
        self::assertSame(0, DB::table('tax_codes')->count());
    }

    public function test_purchase_settings_selectors_are_tenant_and_type_safe_and_save_ignores_body_tenant(): void
    {
        $now = now();
        DB::table('journals')->insert([
            ['id' => '76000000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'code' => 'INK', 'name' => 'Inkoop', 'type' => 'purchase', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '76000000-0000-4000-8000-000000000002', 'administration_id' => self::B, 'code' => 'BINK', 'name' => 'B Inkoop', 'type' => 'purchase', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('ledger_accounts')->insert([
            ['id' => '76000000-0000-4000-8000-000000000003', 'administration_id' => self::A, 'code' => 'AP', 'name' => 'Crediteuren', 'type' => 'liability', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '76000000-0000-4000-8000-000000000004', 'administration_id' => self::A, 'code' => 'IVAT', 'name' => '<script>input</script>', 'type' => 'asset', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '76000000-0000-4000-8000-000000000005', 'administration_id' => self::B, 'code' => 'BAP', 'name' => 'B Crediteuren', 'type' => 'liability', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->get('/settings/administration')->assertOk()
            ->assertSee('INK – Inkoop')->assertDontSee('BINK – B Inkoop')
            ->assertSee('AP – Crediteuren')->assertDontSee('BAP – B Crediteuren')
            ->assertSee('&lt;script&gt;input&lt;/script&gt;', false)->assertDontSee('<script>input</script>', false);

        $this->put('/settings/administration/purchase-posting', [
            'administration_id' => self::B,
            'purchase_journal_id' => '76000000-0000-4000-8000-000000000001',
            'accounts_payable_ledger_account_id' => '76000000-0000-4000-8000-000000000003',
            'input_vat_ledger_account_id' => '76000000-0000-4000-8000-000000000004',
        ])->assertRedirect(route('settings.administration.edit'));
        $this->assertDatabaseHas('purchase_posting_configurations', ['administration_id' => self::A]);
        $this->assertDatabaseMissing('purchase_posting_configurations', ['administration_id' => self::B]);
    }

    public function test_provisioned_configuration_and_safe_same_tenant_options_are_displayed_and_saved(): void
    {
        $masterData = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::A));
        (new EloquentLedgerAccountRepository)->save($this->administration(self::A), new LedgerAccount(
            new LedgerAccountId(new Uuid('73000000-0000-4000-8000-000000000001')),
            new LedgerAccountCode('1310'),
            new LedgerAccountName('<script>alert(1)</script>'),
            LedgerAccountType::Asset,
            LedgerAccountStatus::Active,
        ));
        $other = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::B));

        $this->get('/settings/administration')->assertOk()
            ->assertSee('Geldig')
            ->assertSee('VERK – Verkoop')
            ->assertSee('1300 – Debiteuren')
            ->assertSee('8000 – Omzet')
            ->assertSee('1600 – Af te dragen btw')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($other->salesJournal->id()->toString());

        $this->put('/settings/administration/sales-posting', [
            'administration_id' => self::B,
            'sales_journal_id' => $masterData->salesJournal->id()->toString(),
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect(route('settings.administration.edit'))->assertSessionHas('status', 'Verkoopboekingsinstellingen opgeslagen.');

        self::assertSame(1, DB::table('sales_posting_configurations')->where('administration_id', self::A)->count());
        self::assertSame(1, DB::table('sales_posting_configurations')->where('administration_id', self::B)->count());
    }

    public function test_malformed_cross_tenant_and_inactive_references_are_safe_and_preserve_old_configuration(): void
    {
        $masterData = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::A));
        $other = $this->app->make(DevelopmentAccountingMasterDataProvisioner::class)->provision($this->administration(self::B));
        $before = (array) DB::table('sales_posting_configurations')->where('administration_id', self::A)->first();

        $this->from('/settings/administration')->put('/settings/administration/sales-posting', [
            'sales_journal_id' => 'not-a-uuid',
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect('/settings/administration')->assertSessionHasErrors('sales_journal_id');

        $this->from('/settings/administration')->put('/settings/administration/sales-posting', [
            'sales_journal_id' => $other->salesJournal->id()->toString(),
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect('/settings/administration')->assertSessionHasErrors('sales_posting');

        DB::table('ledger_accounts')->where('id', $masterData->revenue->id()->toString())->update(['status' => 'inactive']);
        $this->get('/settings/administration')->assertOk()
            ->assertSee('Ongeldig – aandacht vereist')
            ->assertSee('Huidige omzetrekening')
            ->assertSee('8000 – Omzet');
        $this->from('/settings/administration')->put('/settings/administration/sales-posting', [
            'sales_journal_id' => $masterData->salesJournal->id()->toString(),
            'accounts_receivable_ledger_account_id' => $masterData->accountsReceivable->id()->toString(),
            'revenue_ledger_account_id' => $masterData->revenue->id()->toString(),
            'output_vat_ledger_account_id' => $masterData->outputVat->id()->toString(),
        ])->assertRedirect('/settings/administration')->assertSessionHasErrors('sales_posting');

        self::assertSame($before, (array) DB::table('sales_posting_configurations')->where('administration_id', self::A)->first());
    }

    public function test_accounting_masterdata_web_lifecycle_is_safe_tenant_scoped_and_escaped(): void
    {
        $this->post('/settings/journals', ['code' => 'verk', 'name' => '<script>Sales</script>', 'type' => 'sales', 'administration_id' => self::B])->assertRedirect(route('settings.journals.index'));
        $journalId = (string) DB::table('journals')->where('administration_id', self::A)->value('id');
        $this->get('/settings/journals')->assertOk()->assertSee('VERK')->assertSee('&lt;script&gt;Sales&lt;/script&gt;', false)->assertDontSee('<script>Sales</script>', false);
        $this->post('/settings/journals', ['code' => 'VERK', 'name' => 'Duplicate', 'type' => 'general'])->assertSessionHasErrors('master_data');
        $this->put('/settings/journals/'.$journalId, ['name' => 'Verkoop gewijzigd', 'code' => 'HACK', 'type' => 'general'])->assertRedirect(route('settings.journals.index'));
        $this->post('/settings/journals/'.$journalId.'/deactivate')->assertRedirect(route('settings.journals.index'));
        $this->assertDatabaseHas('journals', ['id' => $journalId, 'administration_id' => self::A, 'code' => 'VERK', 'name' => 'Verkoop gewijzigd', 'type' => 'sales', 'status' => 'inactive']);
        $this->post('/settings/journals/'.$journalId.'/activate')->assertRedirect(route('settings.journals.index'));
        $this->get('/settings/journals/not-a-uuid/edit')->assertNotFound();

        $this->post('/settings/ledger-accounts', ['code' => '1300', 'name' => '<img src=x onerror=alert(1)>', 'type' => 'asset', 'administration_id' => self::B])->assertRedirect(route('settings.ledger-accounts.index'));
        $accountId = (string) DB::table('ledger_accounts')->where('administration_id', self::A)->value('id');
        $this->get('/settings/ledger-accounts')->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->post('/settings/ledger-accounts', ['code' => '1300', 'name' => 'Duplicate', 'type' => 'revenue'])->assertSessionHasErrors('master_data');
        $this->put('/settings/ledger-accounts/'.$accountId, ['name' => 'Debiteuren gewijzigd', 'code' => '9999', 'type' => 'revenue'])->assertRedirect(route('settings.ledger-accounts.index'));
        $this->post('/settings/ledger-accounts/'.$accountId.'/deactivate')->assertRedirect(route('settings.ledger-accounts.index'));
        $this->assertDatabaseHas('ledger_accounts', ['id' => $accountId, 'administration_id' => self::A, 'code' => '1300', 'name' => 'Debiteuren gewijzigd', 'type' => 'asset', 'status' => 'inactive']);
        $this->get('/settings/ledger-accounts/not-a-uuid/edit')->assertNotFound();
        self::assertSame(0, DB::table('journals')->where('administration_id', self::B)->count());
        self::assertSame(0, DB::table('ledger_accounts')->where('administration_id', self::B)->count());
    }

    public function test_empty_administration_can_be_configured_and_post_domestic_and_eu_service_through_product_flow(): void
    {
        $this->post('/settings/journals', ['code' => 'SALE', 'name' => 'Sales journal', 'type' => 'sales'])->assertRedirect(route('settings.journals.index'));
        foreach ([['1300', 'Debiteuren', 'asset'], ['8000', 'Omzet', 'revenue'], ['1600', 'Af te dragen btw', 'liability']] as [$code, $name, $type]) {
            $this->post('/settings/ledger-accounts', ['code' => $code, 'name' => $name, 'type' => $type])->assertRedirect(route('settings.ledger-accounts.index'));
        }
        $journal = (string) DB::table('journals')->where('administration_id', self::A)->where('code', 'SALE')->value('id');
        $accounts = DB::table('ledger_accounts')->where('administration_id', self::A)->pluck('id', 'code');
        $this->get('/settings/administration')->assertOk()->assertSee('SALE – Sales journal')->assertSee('1300 – Debiteuren')->assertSee('8000 – Omzet')->assertSee('1600 – Af te dragen btw');
        $this->put('/settings/administration/sales-posting', [
            'sales_journal_id' => $journal,
            'accounts_receivable_ledger_account_id' => $accounts['1300'],
            'revenue_ledger_account_id' => $accounts['8000'],
            'output_vat_ledger_account_id' => $accounts['1600'],
        ])->assertRedirect(route('settings.administration.edit'));

        $this->postingFixtures();
        $domestic = $this->app->make(PostSalesInvoice::class)->execute($this->administration(self::A), new SalesInvoiceId(new Uuid($this->invoiceId(1))));
        $eu = $this->app->make(PostSalesInvoice::class)->execute($this->administration(self::A), new SalesInvoiceId(new Uuid($this->invoiceId(2))));
        self::assertSame(PostSalesInvoiceStatus::Success, $domestic->status());
        self::assertSame(PostSalesInvoiceStatus::Success, $eu->status());
        self::assertSame(3, DB::table('journal_entry_lines')->where('journal_entry_id', $domestic->journalEntryId()?->toString())->count());
        self::assertSame(2, DB::table('journal_entry_lines')->where('journal_entry_id', $eu->journalEntryId()?->toString())->count());
        self::assertSame('0', DB::table('tax_postings')->where('source_document_id', $this->invoiceId(2))->value('tax_amount'));
        $historicalLines = DB::table('journal_entry_lines')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        $this->put('/settings/journals/'.$journal, ['name' => 'Renamed sales journal'])->assertRedirect(route('settings.journals.index'));
        $this->put('/settings/ledger-accounts/'.$accounts['8000'], ['name' => 'Renamed revenue'])->assertRedirect(route('settings.ledger-accounts.index'));
        self::assertSame($historicalLines, DB::table('journal_entry_lines')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all());
    }

    public function test_operational_banking_settings_flow_is_authorized_tenant_safe_filtered_and_escaped(): void
    {
        $this->get('/settings/administration')->assertOk()
            ->assertSee('Operationele bankrekeningen')
            ->assertSee('Nog geen operationele bankrekeningen ingesteld')
            ->assertSee('actief Bank-dagboek');

        $this->post('/settings/journals', ['code' => 'BNK', 'name' => 'Bank', 'type' => 'bank'])->assertRedirect(route('settings.journals.index'));
        $this->post('/settings/journals', ['code' => 'GEN', 'name' => 'General', 'type' => 'general'])->assertRedirect(route('settings.journals.index'));
        $this->post('/settings/ledger-accounts', ['code' => '1100', 'name' => '<script>Bank</script>', 'type' => 'asset'])->assertRedirect(route('settings.ledger-accounts.index'));
        $this->post('/settings/ledger-accounts', ['code' => '2100', 'name' => 'Liability', 'type' => 'liability'])->assertRedirect(route('settings.ledger-accounts.index'));
        $this->post('/settings/administration/bank-accounts', ['administration_id' => self::B, 'iban' => 'nl91abna0417164300', 'bic' => '', 'account_holder' => 'Demo Holder', 'label' => '<script>Main</script>'])
            ->assertRedirect(route('settings.administration.edit'));

        $bankId = (string) DB::table('administration_bank_accounts')->where('administration_id', self::A)->value('id');
        $journalId = (string) DB::table('journals')->where('administration_id', self::A)->where('code', 'BNK')->value('id');
        $ledgerId = (string) DB::table('ledger_accounts')->where('administration_id', self::A)->where('code', '1100')->value('id');
        self::assertSame(0, DB::table('administration_bank_accounts')->where('administration_id', self::B)->count());
        $this->get('/settings/administration')->assertOk()
            ->assertSee('&lt;script&gt;Main&lt;/script&gt;', false)->assertDontSee('<script>Main</script>', false)
            ->assertSee('BNK – Bank')->assertDontSee('GEN – General')
            ->assertSee('&lt;script&gt;Bank&lt;/script&gt;', false);

        $this->put("/settings/administration/bank-accounts/{$bankId}", ['account_holder' => 'Renamed', 'label' => 'Updated', 'iban' => 'NL00HACK'])->assertRedirect(route('settings.administration.edit'));
        $this->assertDatabaseHas('administration_bank_accounts', ['id' => $bankId, 'iban' => 'NL91ABNA0417164300', 'account_holder' => 'Renamed', 'label' => 'Updated']);
        $this->put("/settings/administration/bank-accounts/{$bankId}/configuration", ['administration_id' => self::B, 'bank_journal_id' => $journalId, 'bank_ledger_account_id' => $ledgerId])->assertRedirect(route('settings.administration.edit'));
        $this->assertDatabaseHas('banking_posting_configurations', ['administration_id' => self::A, 'administration_bank_account_id' => $bankId]);
        $this->post("/settings/administration/bank-accounts/{$bankId}/deactivate")->assertRedirect(route('settings.administration.edit'));
        $this->assertDatabaseHas('administration_bank_accounts', ['id' => $bankId, 'status' => 'inactive']);
        $this->post("/settings/administration/bank-accounts/{$bankId}/activate")->assertRedirect(route('settings.administration.edit'));
        $this->put('/settings/administration/bank-accounts/not-a-uuid', ['account_holder' => 'Safe', 'label' => 'Safe'])->assertNotFound();

        DB::table('administration_membership_roles')->where('membership_id', self::MEMBERSHIP)->update(['active' => false]);
        $this->post('/settings/administration/bank-accounts', ['iban' => 'NL02ABNA0123456789', 'account_holder' => 'Denied', 'label' => 'Denied'])->assertForbidden();
    }

    private function postingFixtures(): void
    {
        $now = now();
        DB::table('relations')->insert(['id' => $this->fixtureId(20), 'administration_id' => self::A, 'code' => 'REL1', 'display_name' => 'Customer', 'vat_identification_number' => 'DE123456789', 'fiscal_jurisdiction' => 'DE', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => $this->fixtureId(30), 'administration_id' => self::A, 'relation_id' => $this->fixtureId(20), 'customer_number' => 'C000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_codes')->insert([
            ['id' => $this->fixtureId(71), 'administration_id' => self::A, 'code' => 'BTW21', 'name' => 'BTW 21%', 'rate' => '21', 'direction' => 'output', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now],
            ['id' => $this->fixtureId(72), 'administration_id' => self::A, 'code' => 'EUDIENST', 'name' => 'EU service', 'rate' => '0', 'direction' => 'output', 'status' => 'active', 'treatment' => 'reverse_charge_eu_service', 'vat_return_classification' => 'eu_services', 'icp_classification' => 'service', 'created_at' => $now, 'updated_at' => $now],
        ]);
        foreach ([1 => ['21', 71, 'domestic_standard', 'domestic_standard', 'none'], 2 => ['0', 72, 'reverse_charge_eu_service', 'eu_services', 'service']] as $sequence => [$rate, $tax, $treatment, $vat, $icp]) {
            DB::table('sales_invoices')->insert(['id' => $this->invoiceId($sequence), 'administration_id' => self::A, 'sales_invoice_number' => 'F'.sprintf('%06d', $sequence), 'customer_id' => $this->fixtureId(30), 'customer_relation_id_snapshot' => $this->fixtureId(20), 'customer_number_snapshot' => 'C000001', 'customer_name_snapshot' => 'Customer', 'invoice_address_id_snapshot' => $this->fixtureId(40), 'invoice_address_type_snapshot' => 'invoice', 'invoice_address_line_1_snapshot' => 'Street 1', 'invoice_address_line_2_snapshot' => null, 'invoice_postal_code_snapshot' => '1000AA', 'invoice_city_snapshot' => 'Amsterdam', 'invoice_country_code_snapshot' => 'NL', 'customer_vat_id_snapshot' => 'DE123456789', 'customer_fiscal_jurisdiction_snapshot' => 'DE', 'supplier_vat_id_snapshot' => 'NL123456789B01', 'supplier_fiscal_jurisdiction_snapshot' => 'NL', 'supply_date' => '2026-08-25', 'source_order_id' => null, 'currency' => 'EUR', 'invoice_date' => '2026-08-25', 'due_date' => '2026-09-24', 'status' => 'finalized', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('sales_invoice_lines')->insert(['id' => $this->fixtureId(60 + $sequence), 'administration_id' => self::A, 'sales_invoice_id' => $this->invoiceId($sequence), 'description' => 'Line', 'quantity' => '1', 'unit_price_amount' => '100', 'currency' => 'EUR', 'tax_code_id_snapshot' => $this->fixtureId($tax), 'tax_code_snapshot' => $sequence === 1 ? 'BTW21' : 'EUDIENST', 'tax_name_snapshot' => 'Tax', 'tax_rate_snapshot' => $rate, 'tax_direction_snapshot' => 'output', 'tax_treatment_snapshot' => $treatment, 'vat_return_classification_snapshot' => $vat, 'icp_classification_snapshot' => $icp, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function invoiceId(int $sequence): string
    {
        return sprintf('75000000-0000-4000-8000-%012d', $sequence);
    }

    private function fixtureId(int $sequence): string
    {
        return sprintf('74000000-0000-4000-8000-%012d', $sequence);
    }

    private function identity(): void
    {
        $userId = new UserId(new Uuid(self::USER));
        $this->app->make(ProvisionUserAccount::class)->execute($userId, new DisplayName('Settings Manager'), new EmailAddress('posting-settings@example.com'), 'correct-secure-password');
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administrationEntity(self::A, 'POSTA'));
        $administrations->save($this->administrationEntity(self::B, 'POSTB'));
        (new EloquentAdministrationMembershipRepository)->save(new AdministrationMembership(
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP)), $userId, $this->administration(self::A), true,
            new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'),
        ));
        $this->app->make(AdministrationAuthorizationProvisioner::class)->provision();
        (new EloquentMembershipRoleRepository)->save(new MembershipRole(
            new MembershipRoleId(new Uuid('71000000-0000-4000-8000-000000000004')),
            new AdministrationMembershipId(new Uuid(self::MEMBERSHIP)), AdministrationRole::Manager->id(), true,
        ));
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'posting-settings@example.com', 'password' => 'correct-secure-password'])->assertRedirect('/app');
        $this->withSession([EnsureActiveAdministration::SESSION_KEY => self::A]);
    }

    private function administrationEntity(string $id, string $code): Administration
    {
        return new Administration($this->administration($id), new AdministrationCode($code), new AdministrationName('Administration '.$code), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function administration(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}
