<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Application\Identity\ProvisionUserAccount;
use App\Application\Purchasing\GetPurchaseInvoice;
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
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
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

    private const string VAT_PAYABLE = '94000000-0000-4000-8000-000000000012';

    private const string TREATMENT = '94000000-0000-4000-8000-000000000013';

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

    public function test_period_lock_denial_preserves_the_submitted_posting_date_without_financial_side_effects(): void
    {
        $this->assignAll();
        $this->login();
        $this->post('/purchasing/invoices', $this->payload('AP-LOCK-TEST-001'));
        $invoice = DB::table('purchase_invoices')->where('supplier_invoice_number', 'AP-LOCK-TEST-001')->first();
        self::assertNotNull($invoice);
        $this->post('/purchasing/invoices/'.$invoice->id.'/finalize');
        DB::table('accounting_periods')->update(['status' => 'closed']);

        $response = $this->post('/purchasing/invoices/'.$invoice->id.'/post', ['posting_date' => '2026-08-28']);

        $response
            ->assertRedirect('/purchasing/invoices/'.$invoice->id)
            ->assertSessionHas('error', 'De boekingsperiode voor deze inkoopfactuur is gesloten.')
            ->assertSessionHasInput('posting_date', '2026-08-28');
        self::assertSame('finalized', DB::table('purchase_invoices')->where('id', $invoice->id)->value('status'));
        self::assertSame(0, DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoice->id)->count());
        self::assertSame(0, DB::table('journal_entries')->count());
        self::assertSame(0, DB::table('tax_postings')->count());
        self::assertSame(0, DB::table('open_items')->count());

        $this->get('/purchasing/invoices/'.$invoice->id)
            ->assertOk()
            ->assertSee('value="2026-08-28"', false)
            ->assertDontSee('value="'.now()->format('Y-m-d').'"', false);
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

    public function test_purchase_credit_period_lock_denial_preserves_the_submitted_posting_date(): void
    {
        $this->assignAll();
        $this->login();
        $this->post('/purchasing/invoices', $this->payload('CREDIT-LOCK-SOURCE'));
        $invoice = DB::table('purchase_invoices')->where('supplier_invoice_number', 'CREDIT-LOCK-SOURCE')->first();
        self::assertNotNull($invoice);
        $this->post('/purchasing/invoices/'.$invoice->id.'/finalize');
        $this->post('/purchasing/invoices/'.$invoice->id.'/post', ['posting_date' => '2026-08-25']);
        $sourceLineId = DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('id');
        $this->post('/purchasing/credits', [
            'source_invoice_id' => $invoice->id,
            'supplier_credit_invoice_number' => 'CREDIT-LOCK',
            'supplier_credit_date' => '2026-08-26',
            'received_date' => '2026-08-27',
            'source_line_ids' => [$sourceLineId],
        ]);
        $credit = DB::table('purchase_credit_invoices')->where('supplier_credit_invoice_number', 'CREDIT-LOCK')->first();
        self::assertNotNull($credit);
        $this->post('/purchasing/credits/'.$credit->id.'/finalize');
        DB::table('accounting_periods')->update(['status' => 'closed']);

        $response = $this->post('/purchasing/credits/'.$credit->id.'/post', ['posting_date' => '2026-08-28']);

        $response
            ->assertRedirect('/purchasing/credits/'.$credit->id)
            ->assertSessionHas('error', 'De boekingsperiode voor deze creditnota is gesloten.')
            ->assertSessionHasInput('posting_date', '2026-08-28');
        $this->get('/purchasing/credits/'.$credit->id)->assertOk()->assertSee('value="2026-08-28"', false);
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

    public function test_international_invoice_and_credit_web_flow_uses_server_owned_facts_and_historical_group(): void
    {
        $this->assignAll();
        $this->login();
        $this->configureInternationalPurchase();

        $this->get('/purchasing/invoices/create')->assertOk()->assertSee('Aftrekpercentage')->assertSee('Algemene B2B-dienst')->assertDontSee('TaxTreatmentDefinitionId');
        $payload = $this->payload('IPV-WEB-001');
        $payload['lines'][0] = ['description' => '<script>alert(9)</script>', 'quantity' => '1', 'unit_price' => '100', 'ledger_account_id' => self::EXPENSE, 'tax_code_id' => self::OUTPUT, 'international' => '1', 'supply_classification' => 'general_rule_service', 'business_to_business' => '1', 'general_rule_confirmed' => '1', 'deductibility_percentage' => '100', 'deductibility_rationale' => '<script>reason</script>', 'evidence' => '<script>evidence</script>', 'supplier_jurisdiction' => 'FR', 'supplier_vat_id' => 'FAKE', 'administration_id' => '95000000-0000-4000-8000-000000000001'];
        $this->post('/purchasing/invoices', $payload)->assertSessionHasNoErrors();
        $invoice = DB::table('purchase_invoices')->where('supplier_invoice_number', 'IPV-WEB-001')->first();
        self::assertNotNull($invoice);
        $this->post('/purchasing/invoices/'.$invoice->id.'/finalize')->assertSessionHas('status');
        $stored = json_decode((string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('international_tax_input'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('DE', $stored['supplier_jurisdiction']);
        self::assertSame('DE123456789', $stored['supplier_vat_identity']);
        $this->post('/purchasing/invoices/'.$invoice->id.'/post', ['posting_date' => '2026-08-25'])->assertSessionHas('status');
        $this->get('/purchasing/invoices/'.$invoice->id)->assertOk()->assertSee('Internationale btw-boeking')->assertSee('&lt;script&gt;evidence&lt;/script&gt;', false)->assertDontSee('<script>evidence</script>', false)->assertSee('100,00')->assertSee('21,00');

        $lineId = (string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('id');
        $this->get('/purchasing/credits/create?source='.$invoice->id)->assertOk()->assertSee('Historische behandeling')->assertSee('eu_b2b_general_rule_service');
        $this->post('/purchasing/credits', ['source_invoice_id' => $invoice->id, 'supplier_credit_invoice_number' => 'IPV-CR-001', 'supplier_credit_date' => '2026-08-25', 'received_date' => '2026-08-25', 'source_line_ids' => [$lineId]]);
        $credit = DB::table('purchase_credit_invoices')->where('supplier_credit_invoice_number', 'IPV-CR-001')->first();
        self::assertNotNull($credit);
        $this->post('/purchasing/credits/'.$credit->id.'/finalize')->assertSessionHas('status');
        $this->post('/purchasing/credits/'.$credit->id.'/post', ['posting_date' => '2026-08-25'])->assertSessionHas('status');
        self::assertSame('100', DB::table('open_items')->where('id', DB::table('purchase_credit_invoice_postings')->where('purchase_credit_invoice_id', $credit->id)->value('open_item_id'))->value('original_amount'));
        self::assertSame(2, DB::table('tax_postings')->where('source_document_id', $credit->id)->count());
        $this->get('/purchasing/credits/'.$credit->id)->assertOk()->assertSee('Historisch gereverseerde btw-legs')->assertSee('vat_payable')->assertSee('vat_deductible')->assertSee('Leverancierscreditsaldo');
    }

    public function test_user_specified_deductibility_roundtrips_and_finalizes_for_zero_half_and_full_rates(): void
    {
        $this->assignAll();
        $this->login();
        $this->configureInternationalPurchase();

        foreach ([100 => 10000, 50 => 5000, 0 => 0] as $percentage => $basisPoints) {
            $payload = $this->internationalPayload('IPV-RATIONALE-'.$percentage, (string) $percentage);
            $payload['lines'][0]['deductibility_rationale'] = '  Zakelijk gebruik '.$percentage.' procent  ';
            $payload['lines'][0]['evidence'] = '<script>bewijs '.$percentage.'</script>';

            $this->post('/purchasing/invoices', $payload)->assertSessionHasNoErrors();
            $invoice = DB::table('purchase_invoices')->where('supplier_invoice_number', 'IPV-RATIONALE-'.$percentage)->first();
            self::assertNotNull($invoice);
            $stored = json_decode((string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('international_tax_input'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($basisPoints, $stored['deductibility']);
            self::assertSame('Zakelijk gebruik '.$percentage.' procent', $stored['rationale']);
            self::assertSame('<script>bewijs '.$percentage.'</script>', $stored['evidence']);
            self::assertTrue($stored['b2b']);
            self::assertTrue($stored['general_rule']);

            $this->get('/purchasing/invoices/'.$invoice->id.'/edit')
                ->assertOk()
                ->assertSee('value="'.$percentage.'"', false)
                ->assertSee('value="Zakelijk gebruik '.$percentage.' procent"', false)
                ->assertSee('&lt;script&gt;bewijs '.$percentage.'&lt;/script&gt;', false)
                ->assertDontSee('<script>bewijs '.$percentage.'</script>', false);
            $this->post('/purchasing/invoices/'.$invoice->id.'/finalize')->assertSessionHas('status');
            $snapshot = json_decode((string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('tax_treatment_definition_snapshot'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('eu_b2b_general_rule_service', $snapshot['type']);
            $finalized = $this->app->make(GetPurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($invoice->id)));
            self::assertSame($basisPoints, $finalized?->lines()[0]->treatmentSnapshot()?->deductibility->value());
            self::assertSame('Zakelijk gebruik '.$percentage.' procent', $finalized?->lines()[0]->treatmentSnapshot()?->sourceFacts->deductibilityRationale);
            self::assertSame('<script>bewijs '.$percentage.'</script>', $finalized?->lines()[0]->treatmentSnapshot()?->sourceFacts->evidence);
        }

        self::assertSame(0, DB::table('purchase_invoice_postings')->count());
        self::assertSame(0, DB::table('journal_entries')->count());
        self::assertSame(0, DB::table('tax_postings')->count());
        self::assertSame(0, DB::table('open_items')->count());
    }

    public function test_empty_whitespace_and_evidence_only_rationale_are_rejected_without_create_or_update_mutation(): void
    {
        $this->assignAll();
        $this->login();
        $this->configureInternationalPurchase();

        foreach ([null, '', '   '] as $index => $rationale) {
            $payload = $this->internationalPayload('IPV-RATIONALE-INVALID-'.$index, '100');
            $payload['lines'][0]['evidence'] = 'Bewijs is niet de aftrekonderbouwing';
            if ($rationale === null) {
                unset($payload['lines'][0]['deductibility_rationale']);
            } else {
                $payload['lines'][0]['deductibility_rationale'] = $rationale;
            }

            $this->post('/purchasing/invoices', $payload)
                ->assertSessionHasErrors('lines.0.deductibility_rationale')
                ->assertSessionHasInput('lines.0.tax_code_id', self::OUTPUT)
                ->assertSessionHasInput('lines.0.deductibility_percentage', '100')
                ->assertSessionHasInput('lines.0.evidence', 'Bewijs is niet de aftrekonderbouwing')
                ->assertSessionHasInput('lines.0.business_to_business', '1')
                ->assertSessionHasInput('lines.0.general_rule_confirmed', '1');
        }
        self::assertSame(0, DB::table('purchase_invoices')->count());

        $valid = $this->internationalPayload('IPV-RATIONALE-UPDATE', '50');
        $this->post('/purchasing/invoices', $valid)->assertSessionHasNoErrors();
        $invoice = DB::table('purchase_invoices')->where('supplier_invoice_number', 'IPV-RATIONALE-UPDATE')->first();
        self::assertNotNull($invoice);
        $before = DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('international_tax_input');
        $valid['lines'][0]['deductibility_rationale'] = '   ';
        $this->put('/purchasing/invoices/'.$invoice->id, $valid)->assertSessionHasErrors('lines.0.deductibility_rationale');
        self::assertSame($before, DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('international_tax_input'));
    }

    public function test_goods_evidence_requirement_remains_separate_from_deductibility_rationale(): void
    {
        $this->assignAll();
        $this->login();
        $this->configureInternationalPurchase();
        DB::table('tax_codes')->where('id', self::OUTPUT)->update(['treatment' => 'intra_community_goods', 'vat_return_classification' => 'intra_community_supplies', 'icp_classification' => 'goods_supply']);
        DB::table('tax_treatment_definitions')->where('id', self::TREATMENT)->update(['treatment_type' => 'eu_goods_acquisition_nl']);
        $payload = $this->internationalPayload('IPV-GOODS-NO-EVIDENCE', '100');
        $payload['lines'][0]['supply_classification'] = 'goods';
        $payload['lines'][0]['arrives_in_netherlands'] = '1';
        unset($payload['lines'][0]['evidence']);

        $this->post('/purchasing/invoices', $payload)->assertSessionHasNoErrors();
        $invoice = DB::table('purchase_invoices')->where('supplier_invoice_number', 'IPV-GOODS-NO-EVIDENCE')->first();
        self::assertNotNull($invoice);
        $this->post('/purchasing/invoices/'.$invoice->id.'/finalize')
            ->assertSessionHas('error', 'De fiscale gegevens of vereiste bewijsinformatie zijn onvolledig.');
        self::assertSame('draft', DB::table('purchase_invoices')->where('id', $invoice->id)->value('status'));
        self::assertNull(DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoice->id)->value('tax_treatment_definition_snapshot'));
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

    private function internationalPayload(string $number, string $percentage): array
    {
        $payload = $this->payload($number);
        $payload['lines'][0] = ['description' => 'International purchase', 'quantity' => '1', 'unit_price' => '100', 'ledger_account_id' => self::EXPENSE, 'tax_code_id' => self::OUTPUT, 'international' => '1', 'supply_classification' => 'general_rule_service', 'business_to_business' => '1', 'general_rule_confirmed' => '1', 'deductibility_percentage' => $percentage, 'deductibility_rationale' => 'Zakelijk gebruik', 'evidence' => 'Algemene B2B-dienst'];

        return $payload;
    }

    private function configureInternationalPurchase(): void
    {
        DB::table('administrations')->where('id', self::ADMIN)->update(['organisation_vat_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'NL']);
        DB::table('relations')->where('id', self::RELATION)->update(['vat_identification_number' => 'DE123456789', 'fiscal_jurisdiction' => 'DE']);
        DB::table('tax_codes')->where('id', self::OUTPUT)->update(['treatment' => 'reverse_charge_eu_service', 'vat_return_classification' => 'eu_services', 'icp_classification' => 'service']);
        DB::table('ledger_accounts')->insert(['id' => self::VAT_PAYABLE, 'administration_id' => self::ADMIN, 'code' => '1620', 'name' => 'VAT payable', 'type' => 'liability', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('purchase_posting_configurations')->where('administration_id', self::ADMIN)->update(['vat_payable_ledger_account_id' => self::VAT_PAYABLE]);
        DB::table('tax_treatment_definitions')->insert(['id' => self::TREATMENT, 'administration_id' => self::ADMIN, 'tax_code_id' => self::OUTPUT, 'version' => 1, 'treatment_type' => 'eu_b2b_general_rule_service', 'jurisdiction' => 'NL', 'vat_rate' => '21', 'supplier_vat_mode' => 'self_assessed', 'deductibility_policy' => 'user_specified_line_rate', 'leg_definitions' => json_encode([
            ['role' => 'vat_payable', 'direction' => 'output', 'reporting_classification' => 'eu_general_service_due_4b', 'ledger_account_role' => 'vat_payable_control', 'emit_when_zero' => false],
            ['role' => 'vat_deductible', 'direction' => 'input', 'reporting_classification' => 'deductible_input_5b', 'ledger_account_role' => 'input_vat_control', 'emit_when_zero' => false],
        ], JSON_THROW_ON_ERROR), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
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
