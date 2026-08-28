<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Purchasing\CancelPurchaseCreditInvoice;
use App\Application\Purchasing\CreatePurchaseCreditInvoice;
use App\Application\Purchasing\FinalizePurchaseCreditInvoice;
use App\Application\Purchasing\GetPurchaseCreditInvoice;
use App\Application\Purchasing\PurchaseCreditClock;
use App\Application\Purchasing\PurchaseCreditDraftInput;
use App\Application\Purchasing\PurchaseCreditMutationResult;
use App\Application\Purchasing\UpdateDraftPurchaseCreditInvoice;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class PurchaseCreditApplicationContractsTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'a1000000-0000-4000-8000-000000000001';

    private const USER = 'a1000000-0000-4000-8000-000000000002';

    private const RELATION = 'a1000000-0000-4000-8000-000000000003';

    private const SUPPLIER = 'a1000000-0000-4000-8000-000000000004';

    private const INVOICE = 'a1000000-0000-4000-8000-000000000005';

    private const LINE = 'a1000000-0000-4000-8000-000000000006';

    private const JOURNAL = 'a1000000-0000-4000-8000-000000000007';

    private const ENTRY = 'a1000000-0000-4000-8000-000000000008';

    private const EXPENSE = 'a1000000-0000-4000-8000-000000000009';

    private const VAT = 'a1000000-0000-4000-8000-000000000010';

    private const AP = 'a1000000-0000-4000-8000-000000000011';

    private const BASE_LINE = 'a1000000-0000-4000-8000-000000000012';

    private const VAT_LINE = 'a1000000-0000-4000-8000-000000000013';

    private const AP_LINE = 'a1000000-0000-4000-8000-000000000014';

    private const OPEN_ITEM = 'a1000000-0000-4000-8000-000000000015';

    private const TAX_CODE = 'a1000000-0000-4000-8000-000000000016';

    private const TAX_POSTING = 'a1000000-0000-4000-8000-000000000017';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
        $this->app->instance(PurchaseCreditClock::class, new class implements PurchaseCreditClock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-08-28 12:00:00');
            }
        });
    }

    public function test_create_roundtrip_finalize_cancel_and_financial_boundary(): void
    {
        $before = $this->financialCounts();
        $input = $this->input('Credit-X');
        $created = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $input, $this->actor());
        self::assertSame(PurchaseCreditMutationResult::Success, $created->status);
        $credit = $this->app->make(GetPurchaseCreditInvoice::class)->execute($this->admin(), $created->id);
        self::assertSame('Credit-X', $credit?->number()->value());
        self::assertSame('121', $credit?->grossTotal()->amount());
        self::assertSame(self::LINE, $credit?->lines()[0]->sourcePurchaseInvoiceLineId()?->toString());
        self::assertSame(self::TAX_POSTING, $credit?->lines()[0]->sourceTaxPostingId()?->toString());
        self::assertSame(self::OPEN_ITEM, $credit?->sourcePayableOpenItemId()?->toString());
        self::assertSame(PurchaseCreditMutationResult::Success, $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor()));
        self::assertSame(PurchaseCreditMutationResult::AlreadyFinalized, $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor()));
        $final = $this->app->make(GetPurchaseCreditInvoice::class)->execute($this->admin(), $created->id);
        self::assertSame(PurchaseCreditInvoiceStatus::Finalized, $final?->status());
        self::assertSame(self::USER, $final?->finalizedBy()?->toString());
        self::assertSame($before, $this->financialCounts());

        $other = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Credit-Y'), $this->actor());
        self::assertSame(PurchaseCreditMutationResult::Success, $this->app->make(CancelPurchaseCreditInvoice::class)->execute($this->admin(), $other->id, $this->actor()));
        self::assertSame(PurchaseCreditInvoiceStatus::Cancelled, $this->app->make(GetPurchaseCreditInvoice::class)->execute($this->admin(), $other->id)?->status());
        self::assertSame($before, $this->financialCounts());
    }

    public function test_duplicate_identity_invalid_lines_and_empty_draft_finalize_are_typed(): void
    {
        $create = $this->app->make(CreatePurchaseCreditInvoice::class);
        self::assertSame(PurchaseCreditMutationResult::Success, $create->execute($this->admin(), $this->input('Case-1'), $this->actor())->status);
        self::assertSame(PurchaseCreditMutationResult::DuplicateSupplierCreditInvoice, $create->execute($this->admin(), $this->input('Case-1'), $this->actor())->status);
        self::assertSame(PurchaseCreditMutationResult::Success, $create->execute($this->admin(), $this->input('case-1'), $this->actor())->status);
        $duplicateLines = new PurchaseCreditDraftInput(new PurchaseInvoiceId(new Uuid(self::INVOICE)), new PurchaseCreditInvoiceNumber('Dup-lines'), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), [new PurchaseInvoiceLineId(new Uuid(self::LINE)), new PurchaseInvoiceLineId(new Uuid(self::LINE))]);
        self::assertSame(PurchaseCreditMutationResult::InvalidLines, $create->execute($this->admin(), $duplicateLines, $this->actor())->status);
        $empty = $create->execute($this->admin(), new PurchaseCreditDraftInput(new PurchaseInvoiceId(new Uuid(self::INVOICE)), new PurchaseCreditInvoiceNumber('Empty'), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), []), $this->actor());
        self::assertSame(PurchaseCreditMutationResult::InvalidLines, $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $empty->id, $this->actor()));
    }

    public function test_real_mysql_duplicate_create_rename_and_double_finalize_are_serialized(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for PurchaseCredit concurrency tests.');
        }
        DB::commit();
        $createResults = $this->forkResults('pc-create-', fn (): string => $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Race-create'), $this->actor())->status->name);
        sort($createResults);
        self::assertSame(['DuplicateSupplierCreditInvoice', 'Success'], $createResults);
        self::assertSame(1, DB::table('purchase_credit_invoices')->where('supplier_credit_invoice_number', 'Race-create')->count());

        $left = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Rename-left'), $this->actor());
        $right = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Rename-right'), $this->actor());
        $renameResults = $this->forkResults('pc-rename-', function (int $index) use ($left, $right): string {
            $id = $index === 0 ? $left->id : $right->id;

            return $this->app->make(UpdateDraftPurchaseCreditInvoice::class)->execute($this->admin(), $id, $this->input('Race-rename'))->name;
        });
        sort($renameResults);
        self::assertSame(['DuplicateSupplierCreditInvoice', 'Success'], $renameResults);
        self::assertSame(1, DB::table('purchase_credit_invoices')->where('supplier_credit_invoice_number', 'Race-rename')->count());

        $finalize = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Race-finalize'), $this->actor());
        $finalizeResults = $this->forkResults('pc-finalize-', fn (): string => $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $finalize->id, $this->actor())->name);
        sort($finalizeResults);
        self::assertSame(['AlreadyFinalized', 'Success'], $finalizeResults);
        self::assertSame(self::USER, DB::table('purchase_credit_invoices')->where('id', $finalize->id->toString())->value('finalized_by'));
        self::assertSame(1, DB::table('purchase_credit_invoice_lines')->where('purchase_credit_invoice_id', $finalize->id->toString())->count());
        $this->cleanupCommittedFixtures();
        DB::beginTransaction();
    }

    private function input(string $number): PurchaseCreditDraftInput
    {
        return new PurchaseCreditDraftInput(new PurchaseInvoiceId(new Uuid(self::INVOICE)), new PurchaseCreditInvoiceNumber($number), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), [new PurchaseInvoiceLineId(new Uuid(self::LINE))]);
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::A));
    }

    private function actor(): UserId
    {
        return new UserId(new Uuid(self::USER));
    }

    private function financialCounts(): array
    {
        return [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(), DB::table('open_item_matches')->count(), DB::table('open_item_settlements')->count()];
    }

    /** @return list<string> */
    private function forkResults(string $prefix, callable $operation): array
    {
        $files = [tempnam(sys_get_temp_dir(), $prefix), tempnam(sys_get_temp_dir(), $prefix)];
        $children = [];
        foreach ($files as $index => $file) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $operation($index));
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
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $results;
    }

    private function cleanupCommittedFixtures(): void
    {
        DB::table('purchase_credit_invoice_lines')->where('administration_id', self::A)->delete();
        DB::table('purchase_credit_invoices')->where('administration_id', self::A)->delete();
        DB::table('purchase_invoice_postings')->where('administration_id', self::A)->delete();
        DB::table('open_items')->where('administration_id', self::A)->delete();
        DB::table('tax_postings')->where('administration_id', self::A)->delete();
        DB::table('journal_entry_lines')->where('administration_id', self::A)->delete();
        DB::table('journal_entries')->where('administration_id', self::A)->delete();
        DB::table('purchase_invoice_lines')->where('administration_id', self::A)->delete();
        DB::table('purchase_invoices')->where('administration_id', self::A)->delete();
        DB::table('journals')->where('administration_id', self::A)->delete();
        DB::table('tax_codes')->where('administration_id', self::A)->delete();
        DB::table('ledger_accounts')->where('administration_id', self::A)->delete();
        DB::table('suppliers')->where('administration_id', self::A)->delete();
        DB::table('relations')->where('administration_id', self::A)->delete();
        DB::table('domain_users')->where('id', self::USER)->delete();
        DB::table('administrations')->where('id', self::A)->delete();
    }

    private function fixtures(): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => self::A, 'code' => 'PC1', 'name' => 'PC1', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Actor', 'email' => 'pc@example.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => self::RELATION, 'administration_id' => self::A, 'code' => 'SUP', 'display_name' => 'Supplier', 'vat_identification_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'NL', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('suppliers')->insert(['id' => self::SUPPLIER, 'administration_id' => self::A, 'relation_id' => self::RELATION, 'supplier_number' => 'S000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::EXPENSE, '4000', 'Expense', 'expense'], [self::VAT, '1520', 'Input VAT', 'asset'], [self::AP, '1600', 'AP', 'liability']] as [$id,$code,$name,$type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => self::A, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('tax_codes')->insert(['id' => self::TAX_CODE, 'administration_id' => self::A, 'code' => 'INBTW21', 'name' => 'Input 21', 'rate' => '21', 'direction' => 'input', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journals')->insert(['id' => self::JOURNAL, 'administration_id' => self::A, 'code' => 'PUR', 'name' => 'Purchase', 'type' => 'purchase', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('purchase_invoices')->insert(['id' => self::INVOICE, 'administration_id' => self::A, 'supplier_id' => self::SUPPLIER, 'supplier_relation_id_snapshot' => self::RELATION, 'supplier_number_snapshot' => 'S000001', 'supplier_name_snapshot' => 'Supplier', 'supplier_vat_id_snapshot' => 'NL123456789B01', 'supplier_jurisdiction_snapshot' => 'NL', 'supplier_invoice_number' => 'INV-1', 'supplier_invoice_date' => '2026-08-10', 'received_date' => '2026-08-11', 'supply_date' => '2026-08-09', 'fiscal_reporting_date' => '2026-08-11', 'due_date' => '2026-09-10', 'currency' => 'EUR', 'address_line_1_snapshot' => 'Street 1', 'address_line_2_snapshot' => null, 'postal_code_snapshot' => '1000AA', 'city_snapshot' => 'Amsterdam', 'country_code_snapshot' => 'NL', 'status' => 'posted', 'finalized_by' => self::USER, 'finalized_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('purchase_invoice_lines')->insert(['id' => self::LINE, 'administration_id' => self::A, 'purchase_invoice_id' => self::INVOICE, 'description' => 'Services', 'quantity' => '1', 'unit_price_amount' => '100', 'currency' => 'EUR', 'ledger_account_id' => self::EXPENSE, 'ledger_account_code_snapshot' => '4000', 'ledger_account_name_snapshot' => 'Expense', 'ledger_account_type_snapshot' => 'expense', 'tax_code_id' => self::TAX_CODE, 'tax_code_snapshot' => 'INBTW21', 'tax_name_snapshot' => 'Input 21', 'tax_rate_snapshot' => '21', 'tax_direction_snapshot' => 'input', 'tax_treatment_snapshot' => 'domestic_standard', 'vat_return_classification_snapshot' => 'domestic_standard', 'icp_classification_snapshot' => 'none', 'net_amount' => '100', 'tax_amount' => '21', 'gross_amount' => '121', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journal_entries')->insert(['id' => self::ENTRY, 'administration_id' => self::A, 'journal_id' => self::JOURNAL, 'posting_date' => '2026-08-11', 'reference' => 'INV-1', 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::BASE_LINE, self::EXPENSE, '100', null], [self::VAT_LINE, self::VAT, '21', null], [self::AP_LINE, self::AP, null, '121']] as [$id,$account,$debit,$credit]) {
            DB::table('journal_entry_lines')->insert(['id' => $id, 'administration_id' => self::A, 'journal_entry_id' => self::ENTRY, 'ledger_account_id' => $account, 'debit_amount' => $debit, 'credit_amount' => $credit, 'currency' => 'EUR', 'description' => 'Posting', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('tax_postings')->insert(['id' => self::TAX_POSTING, 'administration_id' => self::A, 'tax_code_id' => self::TAX_CODE, 'tax_rate' => '21', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'taxable_base' => '100', 'tax_amount' => '21', 'currency' => 'EUR', 'direction' => 'input', 'type' => 'original', 'source_document_type' => 'purchase_invoice', 'source_document_id' => self::INVOICE, 'source_line_id' => self::LINE, 'posting_date' => '2026-08-11', 'journal_entry_id' => self::ENTRY, 'base_journal_entry_line_id' => self::BASE_LINE, 'tax_journal_entry_line_id' => self::VAT_LINE, 'reversed_tax_posting_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('open_items')->insert(['id' => self::OPEN_ITEM, 'administration_id' => self::A, 'relation_id' => self::RELATION, 'journal_entry_id' => self::ENTRY, 'control_ledger_account_id' => self::AP, 'open_item_type' => 'payable', 'side' => 'credit', 'original_amount' => '121', 'currency' => 'EUR', 'opened_on' => '2026-08-11', 'due_date' => '2026-09-10', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('purchase_invoice_postings')->insert(['administration_id' => self::A, 'purchase_invoice_id' => self::INVOICE, 'journal_entry_id' => self::ENTRY, 'open_item_id' => self::OPEN_ITEM, 'created_at' => $now]);
    }
}
