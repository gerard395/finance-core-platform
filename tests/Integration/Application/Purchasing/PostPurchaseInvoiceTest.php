<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Accounting\CloseAccountingPeriod;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemStore;
use App\Application\Accounting\ReopenAccountingPeriod;
use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Application\Fiscal\TaxTreatmentDefinitionSelection;
use App\Application\Fiscal\TaxTreatmentDefinitionSelectionStatus;
use App\Application\Purchasing\CancelPurchaseInvoice;
use App\Application\Purchasing\CreatePurchaseCreditInvoice;
use App\Application\Purchasing\CreatePurchaseInvoice;
use App\Application\Purchasing\CreatePurchaseInvoiceStatus;
use App\Application\Purchasing\FinalizePurchaseCreditInvoice;
use App\Application\Purchasing\FinalizePurchaseInvoice;
use App\Application\Purchasing\FinalizePurchaseInvoiceResult;
use App\Application\Purchasing\PostPurchaseCreditInvoice;
use App\Application\Purchasing\PostPurchaseCreditInvoiceStatus;
use App\Application\Purchasing\PostPurchaseInvoice;
use App\Application\Purchasing\PostPurchaseInvoiceResult;
use App\Application\Purchasing\PostPurchaseInvoiceStatus;
use App\Application\Purchasing\PurchaseCreditDraftInput;
use App\Application\Purchasing\PurchaseCreditMutationResult;
use App\Application\Purchasing\PurchaseInvoiceDraftInput;
use App\Application\Purchasing\PurchaseInvoiceLineInput;
use App\Application\Purchasing\PurchaseInvoiceMasterDataReader;
use App\Application\Purchasing\PurchaseInvoicePosting;
use App\Application\Purchasing\PurchaseInvoicePostingRepository;
use App\Application\Purchasing\PurchaseInvoiceRepository;
use App\Application\Purchasing\PurchasePostingConfiguration;
use App\Application\Purchasing\PurchasePostingConfigurationReader;
use App\Application\Purchasing\PurchasePostingConfigurationReadStatus;
use App\Application\Purchasing\PurchasePostingConfigurationStore;
use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentGroupId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Enums\PurchaseSupplyClassification;
use App\Domain\Purchasing\ValueObjects\InternationalPurchaseSourceFacts;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

final class PostPurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN = '93000000-0000-4000-8000-000000000001';

    private const string RELATION = '93000000-0000-4000-8000-000000000002';

    private const string SUPPLIER = '93000000-0000-4000-8000-000000000003';

    private const string USER = '93000000-0000-4000-8000-000000000004';

    private const string EXPENSE = '93000000-0000-4000-8000-000000000005';

    private const string ASSET = '93000000-0000-4000-8000-000000000006';

    private const string AP = '93000000-0000-4000-8000-000000000007';

    private const string VAT = '93000000-0000-4000-8000-000000000008';

    private const string TAX21 = '93000000-0000-4000-8000-000000000009';

    private const string TAX0 = '93000000-0000-4000-8000-000000000010';

    private const string JOURNAL = '93000000-0000-4000-8000-000000000011';

    private const string OTHER_AP = '93000000-0000-4000-8000-000000000012';

    private const string VAT_PAYABLE = '93000000-0000-4000-8000-000000000013';

    private const string TAX_INT = '93000000-0000-4000-8000-000000000014';

    private const string TREATMENT = '93000000-0000-4000-8000-000000000015';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
        $this->createOpenAccountingPeriodFixture(self::ADMIN);
    }

    public function test_posts_multiple_accounts_positive_and_zero_tax_to_exact_journal_tax_and_payable_facts(): void
    {
        $invoiceId = $this->finalized('POST-1');
        $result = $this->postInvoice($invoiceId);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $result->status);
        self::assertSame(PostPurchaseInvoiceStatus::AlreadyPosted, $this->postInvoice($invoiceId)->status);
        $entry = DB::table('journal_entries')->where('id', $result->journalEntryId?->toString())->first();
        self::assertSame('2026-08-25', $entry?->posting_date);
        self::assertSame('POST-1', $entry?->reference);
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $entry?->id)->get();
        self::assertCount(4, $lines);
        self::assertSame('171', (string) $lines->whereNotNull('debit_amount')->sum(fn ($line) => (float) $line->debit_amount));
        self::assertSame('171', (string) $lines->whereNotNull('credit_amount')->sum(fn ($line) => (float) $line->credit_amount));
        self::assertSame(2, DB::table('tax_postings')->where('source_document_id', $invoiceId)->count());
        self::assertSame(1, DB::table('tax_postings')->where('tax_amount', '0')->whereNull('tax_journal_entry_line_id')->count());
        self::assertSame(2, DB::table('tax_postings')->where('direction', 'input')->where('posting_date', '2026-08-22')->count());
        $open = DB::table('open_items')->where('id', $result->openItemId?->toString())->first();
        self::assertSame('payable', $open?->open_item_type);
        self::assertSame('credit', $open?->side);
        self::assertSame('171', $open?->original_amount);
        self::assertSame(self::RELATION, $open?->relation_id);
        self::assertSame('2026-09-20', $open?->due_date);
        self::assertSame(self::AP, $open?->control_ledger_account_id);
        DB::table('purchase_posting_configurations')->where('administration_id', self::ADMIN)->update([
            'accounts_payable_ledger_account_id' => self::OTHER_AP,
        ]);
        self::assertSame(self::AP, DB::table('open_items')->where('id', $open?->id)->value('control_ledger_account_id'));
        self::assertSame(1, DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoiceId)->count());
        self::assertSame('posted', DB::table('purchase_invoices')->where('id', $invoiceId)->value('status'));
        self::assertSame(0, DB::table('open_item_settlements')->count());
        self::assertSame(0, DB::table('open_item_matches')->count());
    }

    #[DataProvider('internationalScenarios')]
    public function test_posts_v1_international_treatments_with_exact_deduction_and_supplier_payable(string $type, int $basisPoints, string $expense, ?string $inputVat, string $payableReporting): void
    {
        DB::table('tax_treatment_definitions')->where('id', self::TREATMENT)->update([
            'treatment_type' => $type,
            'leg_definitions' => json_encode($this->legDefinitions($type), JSON_THROW_ON_ERROR),
        ]);
        if ($type === 'non_eu_b2b_general_rule_service') {
            DB::table('tax_codes')->where('id', self::TAX_INT)->update(['direction' => 'input', 'treatment' => 'outside_scope', 'vat_return_classification' => 'outside_scope', 'icp_classification' => 'none']);
            DB::table('relations')->where('id', self::RELATION)->update(['fiscal_jurisdiction' => 'US', 'vat_identification_number' => null]);
        }
        $invoiceId = $this->internationalFinalized('IPV-'.$type.'-'.$basisPoints, $type, $basisPoints);
        $result = $this->postInvoice($invoiceId);

        self::assertSame(PostPurchaseInvoiceStatus::Success, $result->status);
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $result->journalEntryId?->toString())->get();
        self::assertSame($expense, $lines->firstWhere('ledger_account_id', self::EXPENSE)?->debit_amount);
        self::assertSame('100', $lines->firstWhere('ledger_account_id', self::AP)?->credit_amount);
        self::assertSame('21', $lines->firstWhere('ledger_account_id', self::VAT_PAYABLE)?->credit_amount);
        self::assertSame($inputVat, $lines->firstWhere('ledger_account_id', self::VAT)?->debit_amount);
        self::assertSame('100', DB::table('open_items')->where('id', $result->openItemId?->toString())->value('original_amount'));
        $tax = DB::table('tax_postings')->where('source_document_id', $invoiceId)->orderBy('tax_leg_role')->get();
        self::assertCount($basisPoints === 0 ? 1 : 2, $tax);
        self::assertSame(1, $tax->pluck('tax_treatment_group_id')->unique()->count());
        self::assertSame('2026-08-22', $tax->first()?->posting_date);
        self::assertSame('21', $tax->firstWhere('tax_leg_role', 'vat_payable')?->tax_amount);
        self::assertSame($inputVat, $tax->firstWhere('tax_leg_role', 'vat_deductible')?->tax_amount);
        self::assertSame($payableReporting, $tax->firstWhere('tax_leg_role', 'vat_payable')?->reporting_classification);
        self::assertSame($basisPoints === 0 ? null : 'deductible_input_5b', $tax->firstWhere('tax_leg_role', 'vat_deductible')?->reporting_classification);
    }

    public static function internationalScenarios(): array
    {
        $types = ['eu_goods_acquisition_nl', 'eu_b2b_general_rule_service', 'non_eu_b2b_general_rule_service'];
        $rows = [];
        foreach ($types as $type) {
            $payableReporting = match ($type) {
                'eu_goods_acquisition_nl' => 'eu_acquisition_due_4b',
                'eu_b2b_general_rule_service' => 'eu_general_service_due_4b',
                default => 'non_eu_general_service_due_4a',
            };
            $rows[$type.' full'] = [$type, 10000, '100', '21', $payableReporting];
            $rows[$type.' zero'] = [$type, 0, '121', null, $payableReporting];
            $rows[$type.' half'] = [$type, 5000, '110.5', '10.5', $payableReporting];
        }

        return $rows;
    }

    public function test_international_definition_rate_and_legs_override_stale_legacy_selector_semantics(): void
    {
        DB::table('tax_codes')->where('id', self::TAX_INT)->update(['rate' => '9', 'direction' => 'input', 'treatment' => 'outside_scope', 'vat_return_classification' => 'outside_scope', 'icp_classification' => 'none']);
        DB::table('tax_treatment_definitions')->where('id', self::TREATMENT)->update(['treatment_type' => 'non_eu_b2b_general_rule_service', 'vat_rate' => '21', 'leg_definitions' => json_encode($this->legDefinitions('non_eu_b2b_general_rule_service'), JSON_THROW_ON_ERROR)]);
        DB::table('relations')->where('id', self::RELATION)->update(['fiscal_jurisdiction' => 'US', 'vat_identification_number' => null]);

        $invoiceId = $this->internationalFinalized('IPV-NON-EU-AUTHORITY', 'non_eu_b2b_general_rule_service', 10000);
        $result = $this->postInvoice($invoiceId);

        self::assertSame(PostPurchaseInvoiceStatus::Success, $result->status);
        $stored = json_decode((string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoiceId)->value('tax_treatment_definition_snapshot'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('21', $stored['rate']);
        self::assertSame('non_eu_b2b_general_rule_service', $stored['type']);
        $tax = DB::table('tax_postings')->where('source_document_id', $invoiceId)->get();
        self::assertSame(['deductible_input_5b', 'non_eu_general_service_due_4a'], $tax->pluck('reporting_classification')->sort()->values()->all());
        self::assertSame(['21', '21'], $tax->pluck('tax_amount')->sort()->values()->all());
        self::assertSame('100', DB::table('open_items')->where('id', $result->openItemId?->toString())->value('original_amount'));
    }

    public function test_non_eu_credit_reverses_historical_definition_truth_after_current_selector_changes(): void
    {
        DB::table('tax_codes')->where('id', self::TAX_INT)->update(['direction' => 'input', 'treatment' => 'outside_scope', 'vat_return_classification' => 'outside_scope', 'icp_classification' => 'none']);
        DB::table('tax_treatment_definitions')->where('id', self::TREATMENT)->update(['treatment_type' => 'non_eu_b2b_general_rule_service', 'leg_definitions' => json_encode($this->legDefinitions('non_eu_b2b_general_rule_service'), JSON_THROW_ON_ERROR)]);
        DB::table('relations')->where('id', self::RELATION)->update(['fiscal_jurisdiction' => 'US', 'vat_identification_number' => null]);
        $invoiceId = $this->internationalFinalized('IPV-NON-EU-CREDIT-SOURCE', 'non_eu_b2b_general_rule_service', 10000);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        $originals = DB::table('tax_postings')->where('source_document_id', $invoiceId)->orderBy('tax_leg_role')->get()->keyBy('tax_leg_role');
        $created = $this->createInternationalCredit($invoiceId, 'IPV-NON-EU-CREDIT');
        DB::table('tax_codes')->where('id', self::TAX_INT)->update(['rate' => '0']);
        DB::table('tax_treatment_definitions')->where('id', self::TREATMENT)->update(['active' => false]);

        $result = $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor());

        self::assertSame(PostPurchaseCreditInvoiceStatus::Success, $result->status);
        self::assertSame('100', DB::table('open_items')->where('id', $result->openItemId?->toString())->value('original_amount'));
        $reversals = DB::table('tax_postings')->where('source_document_id', $created->id->toString())->orderBy('tax_leg_role')->get()->keyBy('tax_leg_role');
        foreach (['vat_deductible', 'vat_payable'] as $role) {
            self::assertSame($originals[$role]->id, $reversals[$role]->reversed_tax_posting_id);
            self::assertSame($originals[$role]->reporting_classification, $reversals[$role]->reporting_classification);
            self::assertSame(
                DB::table('journal_entry_lines')->where('id', $originals[$role]->tax_journal_entry_line_id)->value('ledger_account_id'),
                DB::table('journal_entry_lines')->where('id', $reversals[$role]->tax_journal_entry_line_id)->value('ledger_account_id'),
            );
        }
    }

    #[DataProvider('internationalCreditScenarios')]
    public function test_purchase_credit_reverses_complete_historical_international_group(int $basisPoints, string $expenseCredit, ?string $inputVatCredit): void
    {
        $invoiceId = $this->internationalFinalized('IPV-CREDIT-SOURCE-'.$basisPoints, 'eu_goods_acquisition_nl', $basisPoints);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        $sourceLineId = (string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoiceId)->value('id');
        $created = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), new PurchaseCreditDraftInput(
            new PurchaseInvoiceId(new Uuid($invoiceId)),
            new PurchaseCreditInvoiceNumber('IPV-CREDIT-'.$basisPoints),
            new DateTimeImmutable('2026-08-23'),
            new DateTimeImmutable('2026-08-24'),
            [new PurchaseInvoiceLineId(new Uuid($sourceLineId))],
        ), $this->actor());
        self::assertSame(PurchaseCreditMutationResult::Success, $created->status);
        self::assertSame(PurchaseCreditMutationResult::Success, $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor()));

        $result = $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor());

        self::assertSame(PostPurchaseCreditInvoiceStatus::Success, $result->status);
        self::assertSame('100', DB::table('open_items')->where('id', $result->openItemId?->toString())->value('original_amount'));
        $journal = DB::table('journal_entry_lines')->where('journal_entry_id', $result->journalEntryId?->toString())->get();
        self::assertSame($expenseCredit, $journal->firstWhere('ledger_account_id', self::EXPENSE)?->credit_amount);
        self::assertSame('100', $journal->firstWhere('ledger_account_id', self::AP)?->debit_amount);
        self::assertSame('21', $journal->firstWhere('ledger_account_id', self::VAT_PAYABLE)?->debit_amount);
        self::assertSame($inputVatCredit, $journal->firstWhere('ledger_account_id', self::VAT)?->credit_amount);
        $reversals = DB::table('tax_postings')->where('source_document_type', 'purchase_credit_invoice')->get();
        self::assertCount($basisPoints === 0 ? 1 : 2, $reversals);
        self::assertSame($basisPoints === 0 ? ['vat_payable'] : ['vat_deductible', 'vat_payable'], $reversals->pluck('tax_leg_role')->sort()->values()->all());
        self::assertSame($reversals->count(), $reversals->pluck('reversed_tax_posting_id')->unique()->count());
        self::assertSame(1, DB::table('purchase_credit_source_line_claims')->where('purchase_credit_invoice_id', $created->id->toString())->count());
        self::assertSame('100', $result->matchedAmount?->amount());
    }

    public static function internationalCreditScenarios(): array
    {
        return [
            'full deduction' => [10000, '100', '21'],
            'zero deduction' => [0, '121', null],
            'half deduction' => [5000, '110.5', '10.5'],
        ];
    }

    public function test_international_credit_mid_group_failure_rolls_back_every_fact(): void
    {
        $invoiceId = $this->internationalFinalized('IPV-CREDIT-ROLLBACK-SOURCE', 'eu_goods_acquisition_nl', 10000);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        $created = $this->createInternationalCredit($invoiceId, 'IPV-CREDIT-ROLLBACK');
        $before = [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(), DB::table('open_item_matches')->count()];
        $this->app->instance(TaxPostingStore::class, new FailingSecondTaxPostingStore($this->app->make(TaxPostingStore::class)));

        $result = $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor());

        self::assertSame(PostPurchaseCreditInvoiceStatus::PostingFailure, $result->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(), DB::table('open_item_matches')->count()]);
        self::assertSame(0, DB::table('purchase_credit_source_line_claims')->where('purchase_credit_invoice_id', $created->id->toString())->count());
        self::assertSame(0, DB::table('purchase_credit_invoice_postings')->where('purchase_credit_invoice_id', $created->id->toString())->count());
        self::assertSame('finalized', DB::table('purchase_credit_invoices')->where('id', $created->id->toString())->value('status'));
    }

    public function test_international_credit_rejects_an_incomplete_historical_group_before_writes(): void
    {
        $invoiceId = $this->internationalFinalized('IPV-CREDIT-CORRUPT-SOURCE', 'eu_goods_acquisition_nl', 10000);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        $created = $this->createInternationalCredit($invoiceId, 'IPV-CREDIT-CORRUPT');
        $snapshot = json_decode((string) DB::table('purchase_credit_invoice_lines')->where('purchase_credit_invoice_id', $created->id->toString())->value('international_tax_snapshot'), true, flags: JSON_THROW_ON_ERROR);
        $referenced = (string) DB::table('purchase_credit_invoice_lines')->where('purchase_credit_invoice_id', $created->id->toString())->value('source_tax_posting_id');
        $missing = collect($snapshot['original_ids'])->first(static fn (string $id): bool => $id !== $referenced);
        DB::table('tax_postings')->where('id', $missing)->delete();
        $before = [DB::table('journal_entries')->count(), DB::table('open_items')->count(), DB::table('purchase_credit_source_line_claims')->count()];

        $result = $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor());

        self::assertSame(PostPurchaseCreditInvoiceStatus::FinancialStateInvalid, $result->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('open_items')->count(), DB::table('purchase_credit_source_line_claims')->count()]);
        self::assertSame('finalized', DB::table('purchase_credit_invoices')->where('id', $created->id->toString())->value('status'));
    }

    public function test_real_mysql_concurrent_international_credit_double_post_is_idempotent(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $invoiceId = $this->internationalFinalized('IPV-CREDIT-RACE-SOURCE', 'eu_goods_acquisition_nl', 10000);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        $created = $this->createInternationalCredit($invoiceId, 'IPV-CREDIT-RACE');
        DB::commit();
        $results = $this->race(fn (): string => $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor())->status->name, fn (): string => $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor())->status->name);
        sort($results);
        self::assertSame(['AlreadyPosted', 'Success'], $results);
        self::assertSame(2, DB::table('tax_postings')->where('source_document_id', $created->id->toString())->count());
        self::assertSame(1, DB::table('purchase_credit_invoice_postings')->where('purchase_credit_invoice_id', $created->id->toString())->count());
        self::assertSame(1, DB::table('purchase_credit_source_line_claims')->where('purchase_credit_invoice_id', $created->id->toString())->count());
        $this->cleanup();
        DB::beginTransaction();
    }

    public function test_real_mysql_competing_international_credits_have_one_complete_winner(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $invoiceId = $this->internationalFinalized('IPV-CREDIT-CLAIM-SOURCE', 'eu_goods_acquisition_nl', 10000);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        $left = $this->createInternationalCredit($invoiceId, 'IPV-CREDIT-CLAIM-A');
        $right = $this->createInternationalCredit($invoiceId, 'IPV-CREDIT-CLAIM-B');
        DB::commit();
        $results = $this->race(fn (): string => $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $left->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor())->status->name, fn (): string => $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $right->id, new PostingDate(new DateTimeImmutable('2026-08-25')), $this->actor())->status->name);
        sort($results);
        self::assertSame(['SourceLineAlreadyCredited', 'Success'], $results);
        self::assertSame(2, DB::table('tax_postings')->where('source_document_type', 'purchase_credit_invoice')->count());
        self::assertSame(1, DB::table('purchase_credit_invoice_postings')->whereIn('purchase_credit_invoice_id', [$left->id->toString(), $right->id->toString()])->count());
        self::assertSame(1, DB::table('purchase_credit_invoices')->whereIn('id', [$left->id->toString(), $right->id->toString()])->where('status', 'finalized')->count());
        $this->cleanup();
        DB::beginTransaction();
    }

    public function test_international_post_requires_server_owned_vat_payable_configuration(): void
    {
        $invoiceId = $this->internationalFinalized('IPV-NO-PAYABLE', 'eu_goods_acquisition_nl', 10000);
        DB::table('purchase_posting_configurations')->where('administration_id', self::ADMIN)->update(['vat_payable_ledger_account_id' => null]);

        self::assertSame(PostPurchaseInvoiceStatus::MissingTaxConfiguration, $this->postInvoice($invoiceId)->status);
        self::assertSame(0, DB::table('journal_entries')->count());
        self::assertSame(0, DB::table('tax_postings')->count());
        self::assertSame(0, DB::table('open_items')->count());
    }

    public function test_finalize_uses_server_owned_party_facts_and_ignores_spoofed_client_identity(): void
    {
        $id = $this->internationalCreated('IPV-SPOOF', 'eu_goods_acquisition_nl', 10000, new InternationalPurchaseSourceFacts('FR', 'BE', 'FAKE-SUPPLIER', 'FAKE-CUSTOMER', PurchaseSupplyClassification::Goods, true, true, false, false, false, false, 'CMR NL', 'Full use'));

        self::assertSame(FinalizePurchaseInvoiceResult::Success, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($id)), $this->actor()));
        $snapshot = json_decode((string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $id)->value('international_tax_input'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('DE', $snapshot['supplier_jurisdiction']);
        self::assertSame('DE123456789', $snapshot['supplier_vat_identity']);
        self::assertSame('NL', $snapshot['customer_jurisdiction']);
        self::assertSame('NL123456789B01', $snapshot['customer_vat_identity']);
    }

    /** @dataProvider missingPartyFacts */
    #[DataProvider('missingPartyFacts')]
    public function test_missing_authoritative_party_facts_are_typed_before_financial_writes(string $table, string $column, string $id): void
    {
        DB::table($table)->where('id', $id)->update([$column => null]);
        $invoiceId = $this->internationalCreated('IPV-MISSING-'.$column, 'eu_goods_acquisition_nl', 10000);

        self::assertSame(FinalizePurchaseInvoiceResult::IncompleteFiscalPartyFacts, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($invoiceId)), $this->actor()));
        self::assertSame(0, DB::table('journal_entries')->count());
        self::assertSame(0, DB::table('tax_postings')->count());
    }

    public static function missingPartyFacts(): array
    {
        return [
            'supplier jurisdiction' => ['relations', 'fiscal_jurisdiction', self::RELATION],
            'supplier VAT' => ['relations', 'vat_identification_number', self::RELATION],
            'customer jurisdiction' => ['administrations', 'fiscal_jurisdiction', self::ADMIN],
            'customer VAT' => ['administrations', 'organisation_vat_number', self::ADMIN],
        ];
    }

    public function test_missing_treatment_and_unsupported_sourcefacts_have_typed_outcomes(): void
    {
        $missing = $this->internationalCreated('IPV-MISSING-TREATMENT', 'eu_goods_acquisition_nl', 10000);
        DB::table('tax_treatment_definitions')->delete();
        self::assertSame(FinalizePurchaseInvoiceResult::MissingTaxTreatment, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($missing)), $this->actor()));

        $this->fixturesTreatment();
        foreach ([
            ['IPV-IMPORT', true, false, false, FinalizePurchaseInvoiceResult::UnsupportedImportCustoms],
            ['IPV-FOREIGN', false, true, false, FinalizePurchaseInvoiceResult::UnsupportedForeignVat],
            ['IPV-SPECIAL', false, false, true, FinalizePurchaseInvoiceResult::UnsupportedTaxTreatment],
        ] as [$number, $import, $foreign, $special, $expected]) {
            $facts = new InternationalPurchaseSourceFacts('XX', 'XX', null, null, PurchaseSupplyClassification::GeneralRuleService, true, false, true, $special, $foreign, $import, 'General rule declaration', 'Full use');
            $id = $this->internationalCreated($number, 'eu_b2b_general_rule_service', 10000, $facts);
            self::assertSame($expected, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($id)), $this->actor()));
        }
        self::assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_direct_application_finalize_still_rejects_missing_user_specified_deductibility_rationale(): void
    {
        $facts = new InternationalPurchaseSourceFacts('DE', 'NL', 'DE123456789', 'NL123456789B01', PurchaseSupplyClassification::GeneralRuleService, true, false, true, false, false, false, 'General rule declaration', null);
        $invoiceId = $this->internationalCreated('IPV-MISSING-RATIONALE', 'eu_b2b_general_rule_service', 10000, $facts);

        self::assertSame(FinalizePurchaseInvoiceResult::InvalidDeductibility, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($invoiceId)), $this->actor()));
        self::assertSame('draft', DB::table('purchase_invoices')->where('id', $invoiceId)->value('status'));
        self::assertNull(DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoiceId)->value('tax_treatment_definition_snapshot'));
        foreach (['journal_entries', 'tax_postings', 'open_items', 'purchase_invoice_postings'] as $table) {
            self::assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_ambiguous_current_treatment_is_typed_integrity_failure(): void
    {
        $invoiceId = $this->internationalCreated('IPV-TREATMENT-INTEGRITY', 'eu_goods_acquisition_nl', 10000);
        $this->app->instance(TaxTreatmentDefinitionRepository::class, new class implements TaxTreatmentDefinitionRepository
        {
            public function append(TaxTreatmentDefinition $definition): void {}

            public function findVersion(AdministrationId $administrationId, TaxTreatmentDefinitionId $definitionId, int $version): ?TaxTreatmentDefinition
            {
                return null;
            }

            public function findActiveForTaxCode(AdministrationId $administrationId, TaxCodeId $taxCodeId): ?TaxTreatmentDefinition
            {
                return null;
            }

            public function resolveActiveForTaxCode(AdministrationId $administrationId, TaxCodeId $taxCodeId): TaxTreatmentDefinitionSelection
            {
                return TaxTreatmentDefinitionSelection::integrityFailure();
            }
        });

        self::assertSame(FinalizePurchaseInvoiceResult::TaxTreatmentIntegrityFailure, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($invoiceId)), $this->actor()));
        self::assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_zero_deduction_does_not_require_active_input_vat_account(): void
    {
        $invoiceId = $this->internationalFinalized('IPV-ZERO-NO-INPUT', 'eu_goods_acquisition_nl', 0);
        DB::table('ledger_accounts')->where('id', self::VAT)->update(['status' => 'inactive']);

        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        self::assertSame(0, DB::table('journal_entry_lines')->where('ledger_account_id', self::VAT)->count());
    }

    public function test_second_administration_cannot_resolve_or_read_first_tenant_purchase_truth(): void
    {
        $other = new AdministrationId(new Uuid('94000000-0000-4000-8000-000000000001'));
        $relation = '94000000-0000-4000-8000-000000000002';
        $supplier = '94000000-0000-4000-8000-000000000003';
        $expense = '94000000-0000-4000-8000-000000000004';
        $ap = '94000000-0000-4000-8000-000000000005';
        $inputVat = '94000000-0000-4000-8000-000000000006';
        $payableVat = '94000000-0000-4000-8000-000000000007';
        $journal = '94000000-0000-4000-8000-000000000008';
        $taxCode = '94000000-0000-4000-8000-000000000009';
        $treatment = '94000000-0000-4000-8000-000000000010';
        $now = now();
        DB::table('administrations')->insert(['id' => $other->toString(), 'code' => 'IPVB', 'name' => 'IPV B', 'base_currency' => 'EUR', 'status' => 'active', 'organisation_vat_number' => 'NL987654321B01', 'fiscal_jurisdiction' => 'NL', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relations')->insert(['id' => $relation, 'administration_id' => $other->toString(), 'code' => 'SUP', 'display_name' => 'Supplier B', 'vat_identification_number' => 'DE987654321', 'fiscal_jurisdiction' => 'DE', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('suppliers')->insert(['id' => $supplier, 'administration_id' => $other->toString(), 'relation_id' => $relation, 'supplier_number' => 'S000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[$expense, '4000', 'Expense', 'expense'], [$ap, '1600', 'AP', 'liability'], [$inputVat, '1520', 'Input VAT', 'asset'], [$payableVat, '1620', 'VAT payable', 'liability']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => $other->toString(), 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => $journal, 'administration_id' => $other->toString(), 'code' => 'INK', 'name' => 'Purchase', 'type' => 'purchase', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_codes')->insert(['id' => $taxCode, 'administration_id' => $other->toString(), 'code' => 'IPV21', 'name' => 'IPV 21', 'rate' => '21', 'direction' => 'output', 'status' => 'active', 'treatment' => 'reverse_charge_eu_service', 'vat_return_classification' => 'eu_services', 'icp_classification' => 'service', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_treatment_definitions')->insert(['id' => $treatment, 'administration_id' => $other->toString(), 'tax_code_id' => $taxCode, 'version' => 1, 'treatment_type' => 'eu_goods_acquisition_nl', 'jurisdiction' => 'NL', 'vat_rate' => '21', 'supplier_vat_mode' => 'self_assessed', 'deductibility_policy' => 'user_specified_line_rate', 'leg_definitions' => json_encode($this->legDefinitions('eu_goods_acquisition_nl'), JSON_THROW_ON_ERROR), 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('purchase_posting_configurations')->insert(['administration_id' => $other->toString(), 'purchase_journal_id' => $journal, 'accounts_payable_ledger_account_id' => $ap, 'input_vat_ledger_account_id' => $inputVat, 'vat_payable_ledger_account_id' => $payableVat, 'created_at' => $now, 'updated_at' => $now]);
        $this->createOpenAccountingPeriodFixture($other->toString());
        $invoiceId = $this->internationalFinalized('IPV-TENANT-A', 'eu_goods_acquisition_nl', 10000);

        self::assertSame(TaxTreatmentDefinitionSelectionStatus::Missing, $this->app->make(TaxTreatmentDefinitionRepository::class)->resolveActiveForTaxCode($other, new TaxCodeId(new Uuid(self::TAX_INT)))->status);
        self::assertNull($this->app->make(PurchaseInvoiceMasterDataReader::class)->activeSupplier($other, new SupplierId(new Uuid(self::SUPPLIER))));
        self::assertNull($this->app->make(PurchaseInvoiceRepository::class)->find($other, new PurchaseInvoiceId(new Uuid($invoiceId))));
        $otherConfiguration = $this->app->make(PurchasePostingConfigurationReader::class)->read($other);
        self::assertSame(PurchasePostingConfigurationReadStatus::Success, $otherConfiguration->status);
        self::assertSame($ap, $otherConfiguration->configuration?->accountsPayableLedgerAccountId->toString());
        self::assertSame($inputVat, $otherConfiguration->configuration?->inputVatLedgerAccountId->toString());
        self::assertSame($payableVat, $otherConfiguration->configuration?->vatPayableLedgerAccountId?->toString());
        self::assertSame([], $this->app->make(TaxPostingReadRepository::class)->findForTreatmentGroup($other, new TaxTreatmentGroupId(new Uuid('94000000-0000-4000-8000-000000000002'))));

        $currency = new Currency('EUR');
        $facts = new InternationalPurchaseSourceFacts('FR', 'BE', 'SPOOF', 'SPOOF', PurchaseSupplyClassification::Goods, true, true, false, false, false, false, 'CMR arrival NL', 'Full use');
        $input = new PurchaseInvoiceDraftInput(new SupplierId(new Uuid($supplier)), new SupplierInvoiceNumber('IPV-TENANT-B'), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), null, new DateTimeImmutable('2026-09-20'), $currency, new PurchaseDocumentAddress(new AddressLine('B street 1'), null, new PostalCode('1000AA'), new City('Amsterdam'), new CountryCode('NL')), [new PurchaseInvoiceLineInput(new LineDescription('B expense'), new Quantity('1'), new Money('100', $currency), new LedgerAccountId(new Uuid($expense)), new TaxCodeId(new Uuid($taxCode)), true, new DeductibilityBasisPoints(10000), $facts)]);
        $created = $this->app->make(CreatePurchaseInvoice::class)->execute($other, $input);
        self::assertSame(CreatePurchaseInvoiceStatus::Success, $created->status);
        self::assertSame(FinalizePurchaseInvoiceResult::Success, $this->app->make(FinalizePurchaseInvoice::class)->execute($other, $created->id, $this->actor()));
        $posted = $this->app->make(PostPurchaseInvoice::class)->execute($other, $created->id, new PostingDate(new DateTimeImmutable('2026-08-25')));
        self::assertSame(PostPurchaseInvoiceStatus::Success, $posted->status);
        self::assertSame(2, DB::table('tax_postings')->where('source_document_id', $created->id->toString())->where('administration_id', $other->toString())->count());
        self::assertSame(0, DB::table('tax_postings')->where('source_document_id', $created->id->toString())->where('administration_id', self::ADMIN)->count());
        self::assertFalse($this->app->make(PurchasePostingConfigurationStore::class)->save(new PurchasePostingConfiguration($other, new JournalId(new Uuid($journal)), new LedgerAccountId(new Uuid(self::AP)), new LedgerAccountId(new Uuid(self::VAT)), new LedgerAccountId(new Uuid(self::VAT_PAYABLE)))));
    }

    public function test_mixed_document_uses_supplier_gross_and_keeps_fiscal_and_accounting_dates_separate(): void
    {
        $currency = new Currency('EUR');
        $facts = new InternationalPurchaseSourceFacts('DE', 'NL', 'DE-VAT', 'NL-VAT', PurchaseSupplyClassification::Goods, true, true, false, false, false, false, 'CMR arrival NL', 'Full business use');
        $input = new PurchaseInvoiceDraftInput(new SupplierId(new Uuid(self::SUPPLIER)), new SupplierInvoiceNumber('IPV-MIXED'), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), null, new DateTimeImmutable('2026-09-20'), $currency, new PurchaseDocumentAddress(new AddressLine('Supplier street 1'), null, new PostalCode('1000AA'), new City('Amsterdam'), new CountryCode('NL')), [
            new PurchaseInvoiceLineInput(new LineDescription('Domestic'), new Quantity('1'), new Money('100', $currency), new LedgerAccountId(new Uuid(self::EXPENSE)), new TaxCodeId(new Uuid(self::TAX21)), true),
            new PurchaseInvoiceLineInput(new LineDescription('EU goods'), new Quantity('1'), new Money('100', $currency), new LedgerAccountId(new Uuid(self::EXPENSE)), new TaxCodeId(new Uuid(self::TAX_INT)), true, new DeductibilityBasisPoints(10000), $facts),
        ]);
        $created = $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $input);
        self::assertNotNull($created->id);
        self::assertSame(FinalizePurchaseInvoiceResult::Success, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), $created->id, $this->actor()));

        $result = $this->app->make(PostPurchaseInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-09-01')));
        self::assertSame(PostPurchaseInvoiceStatus::Success, $result->status);
        self::assertSame('221', DB::table('open_items')->where('id', $result->openItemId?->toString())->value('original_amount'));
        self::assertSame('2026-09-01', DB::table('journal_entries')->where('id', $result->journalEntryId?->toString())->value('posting_date'));
        self::assertSame(3, DB::table('tax_postings')->where('source_document_id', $created->id->toString())->count());
        self::assertSame(3, DB::table('tax_postings')->where('source_document_id', $created->id->toString())->where('posting_date', '2026-08-22')->count());
    }

    public function test_period_denials_are_typed_and_leave_purchase_financial_facts_unchanged(): void
    {
        $invoiceId = $this->finalized('PERIOD-DENY');
        $before = [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count()];
        DB::table('accounting_periods')->where('administration_id', self::ADMIN)->update(['status' => 'closed']);
        self::assertSame(PostPurchaseInvoiceStatus::PeriodClosed, $this->postInvoice($invoiceId)->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count()]);
        DB::table('accounting_periods')->where('administration_id', self::ADMIN)->delete();
        self::assertSame(PostPurchaseInvoiceStatus::NoAccountingPeriod, $this->postInvoice($invoiceId)->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count()]);
    }

    public function test_real_mysql_close_vs_post_is_serializable(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $invoiceId = $this->finalized('CLOSE-RACE');
        $periodId = $this->periodId();
        DB::commit();
        $results = $this->race(
            fn (): string => 'close:'.$this->app->make(CloseAccountingPeriod::class)->execute($this->admin(), $periodId, 'close race', $this->actor(), new DateTimeImmutable('2026-08-25 12:00:00'))->name,
            fn (): string => 'post:'.$this->postInvoice($invoiceId)->status->name,
        );
        self::assertContains('close:Success', $results);
        self::assertTrue(in_array('post:Success', $results, true) || in_array('post:PeriodClosed', $results, true));
        self::assertSame('closed', DB::table('accounting_periods')->where('id', $periodId->toString())->value('status'));
        self::assertSame(in_array('post:Success', $results, true) ? 1 : 0, DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoiceId)->count());
        $this->cleanup();
        DB::beginTransaction();
    }

    public function test_real_mysql_reopen_vs_post_has_no_stale_status_bypass(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $invoiceId = $this->finalized('REOPEN-RACE');
        $periodId = $this->periodId();
        DB::table('accounting_periods')->where('id', $periodId->toString())->update(['status' => 'closed']);
        DB::commit();
        $results = $this->race(
            fn (): string => 'reopen:'.$this->app->make(ReopenAccountingPeriod::class)->execute($this->admin(), $periodId, 'reopen race', $this->actor(), new DateTimeImmutable('2026-08-25 12:00:00'))->name,
            fn (): string => 'post:'.$this->postInvoice($invoiceId)->status->name,
        );
        self::assertContains('reopen:Success', $results);
        self::assertTrue(in_array('post:Success', $results, true) || in_array('post:PeriodClosed', $results, true));
        self::assertSame('open', DB::table('accounting_periods')->where('id', $periodId->toString())->value('status'));
        self::assertSame(in_array('post:Success', $results, true) ? 1 : 0, DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoiceId)->count());
        $this->cleanup();
        DB::beginTransaction();
    }

    public function test_configuration_and_lifecycle_failures_leave_document_and_financial_state_unchanged(): void
    {
        $finalized = $this->finalized('NO-CONFIG');
        DB::table('purchase_posting_configurations')->delete();
        self::assertSame(PostPurchaseInvoiceStatus::ConfigurationMissing, $this->postInvoice($finalized)->status);
        self::assertSame('finalized', DB::table('purchase_invoices')->where('id', $finalized)->value('status'));
        $draft = $this->created('DRAFT');
        self::assertSame(PostPurchaseInvoiceStatus::InvalidState, $this->postInvoice($draft)->status);
        $cancelled = $this->created('CANCELLED');
        $this->app->make(CancelPurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($cancelled)));
        self::assertSame(PostPurchaseInvoiceStatus::InvalidState, $this->postInvoice($cancelled)->status);
        self::assertSame(0, DB::table('journal_entries')->count());
        self::assertSame(0, DB::table('tax_postings')->count());
        self::assertSame(0, DB::table('open_items')->count());
    }

    public function test_current_account_validation_and_historical_supplier_and_tax_truth(): void
    {
        $invoiceId = $this->finalized('HISTORY');
        DB::table('suppliers')->where('id', self::SUPPLIER)->update(['active' => false]);
        DB::table('relations')->where('id', self::RELATION)->update(['display_name' => 'Changed supplier']);
        DB::table('ledger_accounts')->where('id', self::EXPENSE)->update(['name' => 'Changed expense']);
        DB::table('tax_codes')->where('id', self::TAX21)->update(['name' => 'Changed tax', 'rate' => '9', 'treatment' => 'domestic_reduced', 'vat_return_classification' => 'domestic_reduced']);
        self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
        self::assertSame('21', DB::table('tax_postings')->where('source_document_id', $invoiceId)->where('tax_amount', '21')->value('tax_rate'));

        DB::table('suppliers')->where('id', self::SUPPLIER)->update(['active' => true]);
        $invalid = $this->finalized('ACCOUNT-INACTIVE');
        DB::table('ledger_accounts')->where('id', self::ASSET)->update(['status' => 'inactive']);
        self::assertSame(PostPurchaseInvoiceStatus::ConfigurationInvalid, $this->postInvoice($invalid)->status);
    }

    public function test_reduced_exempt_and_outside_scope_snapshots_post_without_live_inference(): void
    {
        foreach ([
            ['9', 'domestic_reduced', 'domestic_reduced'],
            ['0', 'exempt', 'exempt'],
            ['0', 'outside_scope', 'outside_scope'],
        ] as $index => [$rate, $treatment, $classification]) {
            DB::table('tax_codes')->where('id', self::TAX0)->update(['rate' => $rate, 'treatment' => $treatment, 'vat_return_classification' => $classification]);
            $invoiceId = $this->finalized('MATRIX-'.$index);
            self::assertSame(PostPurchaseInvoiceStatus::Success, $this->postInvoice($invoiceId)->status);
            $fact = DB::table('tax_postings')->where('source_document_id', $invoiceId)->where('tax_code_id', self::TAX0)->first();
            self::assertSame($treatment, $fact?->treatment);
            self::assertSame($classification, $fact?->vat_return_classification);
            self::assertSame($rate === '9' ? '4.5' : '0', $fact?->tax_amount);
        }
    }

    public function test_each_deactivated_configuration_reference_is_typed_invalid(): void
    {
        foreach ([['journals', self::JOURNAL], ['ledger_accounts', self::AP], ['ledger_accounts', self::VAT]] as $index => [$table, $id]) {
            $invoiceId = $this->finalized('CONFIG-'.$index);
            DB::table($table)->where('id', $id)->update(['status' => 'inactive']);
            self::assertSame($id === self::VAT ? PostPurchaseInvoiceStatus::MissingTaxConfiguration : PostPurchaseInvoiceStatus::ConfigurationInvalid, $this->postInvoice($invoiceId)->status);
            self::assertSame('finalized', DB::table('purchase_invoices')->where('id', $invoiceId)->value('status'));
            DB::table($table)->where('id', $id)->update(['status' => 'active']);
        }
    }

    public function test_real_mysql_concurrent_double_post_creates_one_complete_financial_set(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $invoiceId = $this->finalized('RACE-POST');
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'purchase-post-'), tempnam(sys_get_temp_dir(), 'purchase-post-')];
        $children = [];
        foreach ($files as $file) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $this->postInvoice($invoiceId)->status->name);
                    exit(0);
                } catch (Throwable $e) {
                    file_put_contents($file, 'ERROR:'.$e->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn ($file) => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($results);
        self::assertSame(['AlreadyPosted', 'Success'], $results);
        self::assertSame(1, DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoiceId)->count());
        self::assertSame(1, DB::table('journal_entries')->count());
        self::assertSame(2, DB::table('tax_postings')->count());
        self::assertSame(1, DB::table('open_items')->count());
        $this->cleanup();
        DB::beginTransaction();
    }

    public function test_real_mysql_concurrent_international_double_post_creates_one_complete_treatment_group(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $invoiceId = $this->internationalFinalized('IPV-RACE', 'eu_goods_acquisition_nl', 10000);
        DB::commit();
        $results = $this->race(fn (): string => $this->postInvoice($invoiceId)->status->name, fn (): string => $this->postInvoice($invoiceId)->status->name);
        sort($results);
        self::assertSame(['AlreadyPosted', 'Success'], $results);
        self::assertSame(1, DB::table('purchase_invoice_postings')->where('purchase_invoice_id', $invoiceId)->count());
        self::assertSame(1, DB::table('journal_entries')->count());
        self::assertSame(2, DB::table('tax_postings')->where('source_document_id', $invoiceId)->count());
        self::assertSame(1, DB::table('tax_postings')->where('source_document_id', $invoiceId)->pluck('tax_treatment_group_id')->unique()->count());
        self::assertSame(1, DB::table('open_items')->count());
        $this->cleanup();
        DB::beginTransaction();
    }

    public function test_every_persistence_stage_failure_rolls_back_all_financial_and_status_facts(): void
    {
        $journal = $this->app->make(JournalEntryStore::class);
        $tax = $this->app->make(TaxPostingStore::class);
        $open = $this->app->make(OpenItemStore::class);
        $link = $this->app->make(PurchaseInvoicePostingRepository::class);
        $invoices = $this->app->make(PurchaseInvoiceRepository::class);

        foreach (['journal', 'tax', 'open', 'link', 'status'] as $stage) {
            $invoiceId = $this->finalized('FAIL-'.strtoupper($stage));
            $this->app->instance(JournalEntryStore::class, $stage === 'journal' ? new FailingJournalEntryStore : $journal);
            $this->app->instance(TaxPostingStore::class, $stage === 'tax' ? new FailingTaxPostingStore : $tax);
            $this->app->instance(OpenItemStore::class, $stage === 'open' ? new FailingOpenItemStore : $open);
            $this->app->instance(PurchaseInvoicePostingRepository::class, $stage === 'link' ? new FailingPurchaseInvoicePostingRepository($link) : $link);
            $this->app->instance(PurchaseInvoiceRepository::class, $stage === 'status' ? new FailingPurchaseInvoiceStatusRepository($invoices) : $invoices);

            self::assertSame(PostPurchaseInvoiceStatus::PostingFailure, $this->postInvoice($invoiceId)->status, $stage);
            self::assertSame('finalized', DB::table('purchase_invoices')->where('id', $invoiceId)->value('status'), $stage);
            self::assertSame(0, DB::table('journal_entries')->count(), $stage);
            self::assertSame(0, DB::table('tax_postings')->count(), $stage);
            self::assertSame(0, DB::table('open_items')->count(), $stage);
            self::assertSame(0, DB::table('purchase_invoice_postings')->count(), $stage);
        }
    }

    public function test_second_international_tax_leg_failure_rolls_back_complete_group_and_financial_set(): void
    {
        $invoiceId = $this->internationalFinalized('IPV-SECOND-LEG-FAIL', 'eu_goods_acquisition_nl', 10000);
        $this->app->instance(TaxPostingStore::class, new FailingSecondTaxPostingStore($this->app->make(TaxPostingStore::class)));

        self::assertSame(PostPurchaseInvoiceStatus::PostingFailure, $this->postInvoice($invoiceId)->status);
        self::assertSame('finalized', DB::table('purchase_invoices')->where('id', $invoiceId)->value('status'));
        foreach (['journal_entries', 'journal_entry_lines', 'tax_postings', 'open_items', 'purchase_invoice_postings'] as $table) {
            self::assertSame(0, DB::table($table)->count(), $table);
        }
    }

    private function created(string $number): string
    {
        return $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $this->input($number))->id?->toString() ?? '';
    }

    private function finalized(string $number): string
    {
        $id = $this->created($number);
        $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($id)), new UserId(new Uuid(self::USER)));

        return $id;
    }

    private function internationalFinalized(string $number, string $type, int $basisPoints): string
    {
        $id = $this->internationalCreated($number, $type, $basisPoints);
        self::assertSame(FinalizePurchaseInvoiceResult::Success, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($id)), $this->actor()));

        return $id;
    }

    private function internationalCreated(string $number, string $type, int $basisPoints, ?InternationalPurchaseSourceFacts $facts = null): string
    {
        $currency = new Currency('EUR');
        $goods = $type === 'eu_goods_acquisition_nl';
        $facts ??= new InternationalPurchaseSourceFacts($type === 'non_eu_b2b_general_rule_service' ? 'GB' : 'DE', 'NL', 'SUPPLIER-VAT', 'CUSTOMER-VAT', $goods ? PurchaseSupplyClassification::Goods : PurchaseSupplyClassification::GeneralRuleService, true, $goods, ! $goods, false, false, false, $goods ? 'Transport document NL arrival' : 'General rule declaration', 'Business use allocation', 'IPV-V1');
        $input = new PurchaseInvoiceDraftInput(new SupplierId(new Uuid(self::SUPPLIER)), new SupplierInvoiceNumber($number), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), null, new DateTimeImmutable('2026-09-20'), $currency, new PurchaseDocumentAddress(new AddressLine('Supplier street 1'), null, new PostalCode('1000AA'), new City('Amsterdam'), new CountryCode('NL')), [
            new PurchaseInvoiceLineInput(new LineDescription('International expense'), new Quantity('1'), new Money('100', $currency), new LedgerAccountId(new Uuid(self::EXPENSE)), new TaxCodeId(new Uuid(self::TAX_INT)), true, new DeductibilityBasisPoints($basisPoints), $facts),
        ]);
        $created = $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $input);
        self::assertSame(CreatePurchaseInvoiceStatus::Success, $created->status);
        $id = $created->id?->toString() ?? '';

        return $id;
    }

    private function postInvoice(string $id): PostPurchaseInvoiceResult
    {
        return $this->app->make(PostPurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($id)), new PostingDate(new DateTimeImmutable('2026-08-25')));
    }

    private function createInternationalCredit(string $invoiceId, string $number): mixed
    {
        $sourceLineId = (string) DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoiceId)->value('id');
        $created = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), new PurchaseCreditDraftInput(new PurchaseInvoiceId(new Uuid($invoiceId)), new PurchaseCreditInvoiceNumber($number), new DateTimeImmutable('2026-08-23'), new DateTimeImmutable('2026-08-24'), [new PurchaseInvoiceLineId(new Uuid($sourceLineId))]), $this->actor());
        self::assertSame(PurchaseCreditMutationResult::Success, $created->status);
        self::assertSame(PurchaseCreditMutationResult::Success, $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor()));

        return $created;
    }

    private function input(string $number): PurchaseInvoiceDraftInput
    {
        $currency = new Currency('EUR');

        return new PurchaseInvoiceDraftInput(new SupplierId(new Uuid(self::SUPPLIER)), new SupplierInvoiceNumber($number), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), null, new DateTimeImmutable('2026-09-20'), $currency, new PurchaseDocumentAddress(new AddressLine('Supplier street 1'), null, new PostalCode('1000AA'), new City('Amsterdam'), new CountryCode('NL')), [
            new PurchaseInvoiceLineInput(new LineDescription('Expense'), new Quantity('1'), new Money('100', $currency), new LedgerAccountId(new Uuid(self::EXPENSE)), new TaxCodeId(new Uuid(self::TAX21)), true),
            new PurchaseInvoiceLineInput(new LineDescription('Asset'), new Quantity('1'), new Money('50', $currency), new LedgerAccountId(new Uuid(self::ASSET)), new TaxCodeId(new Uuid(self::TAX0)), true),
        ]);
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::ADMIN));
    }

    private function actor(): UserId
    {
        return new UserId(new Uuid(self::USER));
    }

    private function periodId(): AccountingPeriodId
    {
        return new AccountingPeriodId(new Uuid((string) DB::table('accounting_periods')->where('administration_id', self::ADMIN)->value('id')));
    }

    /** @return list<string> */
    private function race(callable $left, callable $right): array
    {
        $files = [tempnam(sys_get_temp_dir(), 'ap-post-race-'), tempnam(sys_get_temp_dir(), 'ap-post-race-')];
        $children = [];
        foreach ([$left, $right] as $index => $operation) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($files[$index], $operation());
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($files[$index], 'error:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $results;
    }

    private function fixtures(): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => self::ADMIN, 'code' => 'P3POST', 'name' => 'P3 Post', 'base_currency' => 'EUR', 'status' => 'active', 'organisation_vat_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'NL', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Actor', 'email' => 'post@example.com', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => self::RELATION, 'administration_id' => self::ADMIN, 'code' => 'SUP', 'display_name' => 'Supplier', 'vat_identification_number' => 'DE123456789', 'fiscal_jurisdiction' => 'DE', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('suppliers')->insert(['id' => self::SUPPLIER, 'administration_id' => self::ADMIN, 'relation_id' => self::RELATION, 'supplier_number' => 'S000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::EXPENSE, '4000', 'Expense', 'expense'], [self::ASSET, '0200', 'Asset', 'asset'], [self::AP, '1600', 'Accounts payable', 'liability'], [self::OTHER_AP, '1601', 'Other accounts payable', 'liability'], [self::VAT, '1520', 'Input VAT', 'asset'], [self::VAT_PAYABLE, '1620', 'VAT payable', 'liability']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => self::JOURNAL, 'administration_id' => self::ADMIN, 'code' => 'INK', 'name' => 'Purchase', 'type' => 'purchase', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_codes')->insert([
            ['id' => self::TAX21, 'administration_id' => self::ADMIN, 'code' => 'INBTW21', 'name' => 'Input 21', 'rate' => '21', 'direction' => 'input', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::TAX0, 'administration_id' => self::ADMIN, 'code' => 'INBTW0', 'name' => 'Input 0', 'rate' => '0', 'direction' => 'input', 'status' => 'active', 'treatment' => 'zero_rated', 'vat_return_classification' => 'domestic_zero_rated', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::TAX_INT, 'administration_id' => self::ADMIN, 'code' => 'IPV21', 'name' => 'International reverse charge 21', 'rate' => '21', 'direction' => 'output', 'status' => 'active', 'treatment' => 'reverse_charge_eu_service', 'vat_return_classification' => 'eu_services', 'icp_classification' => 'service', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->fixturesTreatment();
        DB::table('purchase_posting_configurations')->insert(['administration_id' => self::ADMIN, 'purchase_journal_id' => self::JOURNAL, 'accounts_payable_ledger_account_id' => self::AP, 'input_vat_ledger_account_id' => self::VAT, 'vat_payable_ledger_account_id' => self::VAT_PAYABLE, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function fixturesTreatment(): void
    {
        DB::table('tax_treatment_definitions')->insert(['id' => self::TREATMENT, 'administration_id' => self::ADMIN, 'tax_code_id' => self::TAX_INT, 'version' => 1, 'treatment_type' => 'eu_goods_acquisition_nl', 'jurisdiction' => 'NL', 'vat_rate' => '21', 'supplier_vat_mode' => 'self_assessed', 'deductibility_policy' => 'user_specified_line_rate', 'leg_definitions' => json_encode($this->legDefinitions('eu_goods_acquisition_nl'), JSON_THROW_ON_ERROR), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function legDefinitions(string $type): array
    {
        $due = match ($type) {
            'eu_goods_acquisition_nl' => 'eu_acquisition_due_4b',
            'eu_b2b_general_rule_service' => 'eu_general_service_due_4b',
            default => 'non_eu_general_service_due_4a',
        };

        return [
            ['role' => 'vat_payable', 'direction' => 'output', 'reporting_classification' => $due, 'ledger_account_role' => 'vat_payable_control', 'emit_when_zero' => false],
            ['role' => 'vat_deductible', 'direction' => 'input', 'reporting_classification' => 'deductible_input_5b', 'ledger_account_role' => 'input_vat_control', 'emit_when_zero' => false],
        ];
    }

    private function cleanup(): void
    {
        DB::table('accounting_period_status_history')->where('administration_id', self::ADMIN)->delete();
        foreach (['open_item_matches', 'purchase_credit_source_line_claims', 'purchase_credit_invoice_postings', 'purchase_credit_invoice_lines', 'purchase_credit_invoices', 'purchase_invoice_postings', 'open_items'] as $table) {
            DB::table($table)->where('administration_id', self::ADMIN)->delete();
        }
        DB::table('tax_postings')->where('administration_id', self::ADMIN)->where('type', 'reversal')->delete();
        DB::table('tax_postings')->where('administration_id', self::ADMIN)->delete();
        foreach (['journal_entry_lines', 'journal_entries', 'purchase_invoice_lines', 'purchase_invoices', 'purchase_posting_configurations', 'tax_treatment_definitions', 'tax_codes', 'journals', 'ledger_accounts', 'suppliers', 'relations'] as $table) {
            DB::table($table)->where('administration_id', self::ADMIN)->delete();
        }
        DB::table('accounting_periods')->where('administration_id', self::ADMIN)->delete();
        DB::table('book_years')->where('administration_id', self::ADMIN)->delete();
        DB::table('domain_users')->where('id', self::USER)->delete();
        DB::table('administrations')->where('id', self::ADMIN)->delete();
    }
}

final class FailingJournalEntryStore implements JournalEntryStore
{
    public function append(JournalEntry $journalEntry): void
    {
        throw new \RuntimeException('journal failure');
    }
}

final class FailingTaxPostingStore implements TaxPostingStore
{
    public function append(TaxPosting $taxPosting): void
    {
        throw new \RuntimeException('tax failure');
    }
}

final class FailingSecondTaxPostingStore implements TaxPostingStore
{
    private int $calls = 0;

    public function __construct(private readonly TaxPostingStore $delegate) {}

    public function append(TaxPosting $taxPosting): void
    {
        $this->calls++;
        if ($this->calls === 2) {
            throw new \RuntimeException('second tax leg failure');
        }
        $this->delegate->append($taxPosting);
    }
}

final class FailingOpenItemStore implements OpenItemStore
{
    public function append(OpenItem $openItem): void
    {
        throw new \RuntimeException('open item failure');
    }
}

final readonly class FailingPurchaseInvoicePostingRepository implements PurchaseInvoicePostingRepository
{
    public function __construct(private PurchaseInvoicePostingRepository $delegate) {}

    public function findForInvoice(AdministrationId $administrationId, PurchaseInvoiceId $invoiceId): ?PurchaseInvoicePosting
    {
        return $this->delegate->findForInvoice($administrationId, $invoiceId);
    }

    public function append(PurchaseInvoicePosting $posting): bool
    {
        return false;
    }
}

final readonly class FailingPurchaseInvoiceStatusRepository implements PurchaseInvoiceRepository
{
    public function __construct(private PurchaseInvoiceRepository $delegate) {}

    public function create(PurchaseInvoice $invoice): bool
    {
        return $this->delegate->create($invoice);
    }

    public function save(PurchaseInvoice $invoice): bool
    {
        return false;
    }

    public function find(AdministrationId $administrationId, PurchaseInvoiceId $id): ?PurchaseInvoice
    {
        return $this->delegate->find($administrationId, $id);
    }

    public function findForUpdate(AdministrationId $administrationId, PurchaseInvoiceId $id): ?PurchaseInvoice
    {
        return $this->delegate->findForUpdate($administrationId, $id);
    }

    public function list(AdministrationId $administrationId): array
    {
        return $this->delegate->list($administrationId);
    }
}
