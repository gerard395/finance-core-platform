<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Purchasing\CancelPurchaseInvoice;
use App\Application\Purchasing\CancelPurchaseInvoiceResult;
use App\Application\Purchasing\CreatePurchaseInvoice;
use App\Application\Purchasing\CreatePurchaseInvoiceStatus;
use App\Application\Purchasing\FinalizePurchaseInvoice;
use App\Application\Purchasing\FinalizePurchaseInvoiceResult;
use App\Application\Purchasing\GetPurchaseInvoice;
use App\Application\Purchasing\ListPurchaseInvoices;
use App\Application\Purchasing\PurchaseInvoiceClock;
use App\Application\Purchasing\PurchaseInvoiceDraftInput;
use App\Application\Purchasing\PurchaseInvoiceLineInput;
use App\Application\Purchasing\UpdateDraftPurchaseInvoice;
use App\Application\Purchasing\UpdateDraftPurchaseInvoiceResult;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
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
use Tests\TestCase;
use Throwable;

final class PurchaseInvoiceApplicationContractsTest extends TestCase
{
    use RefreshDatabase;

    private const string A = '91000000-0000-4000-8000-000000000001';

    private const string B = '92000000-0000-4000-8000-000000000001';

    private const string SUPPLIER = '91000000-0000-4000-8000-000000000003';

    private const string USER = '91000000-0000-4000-8000-000000000004';

    private const string USER_B = '91000000-0000-4000-8000-000000000007';

    private const string ACCOUNT = '91000000-0000-4000-8000-000000000005';

    private const string TAX = '91000000-0000-4000-8000-000000000006';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
        $this->app->instance(PurchaseInvoiceClock::class, new class implements PurchaseInvoiceClock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-08-25 14:30:00');
            }
        });
    }

    public function test_create_roundtrip_snapshots_totals_list_and_finalize_without_posting_configuration(): void
    {
        $result = $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $this->input(' EXT-001 '));
        self::assertSame(CreatePurchaseInvoiceStatus::Success, $result->status);
        self::assertNotNull($result->id);
        $invoice = $this->app->make(GetPurchaseInvoice::class)->execute($this->admin(), $result->id);
        self::assertNotNull($invoice);
        self::assertSame('EXT-001', $invoice->number()->value());
        self::assertSame('Supplier Original', $invoice->supplierSnapshot()->name->value());
        self::assertSame('2026-08-22', $invoice->fiscalReportingDate()->format('Y-m-d'));
        self::assertSame('100', $invoice->netTotal()->amount());
        self::assertSame('21', $invoice->taxTotal()->amount());
        self::assertSame('121', $invoice->grossTotal()->amount());
        self::assertSame('4000', $invoice->lines()[0]->account()->code->value());
        self::assertSame('INBTW21', $invoice->lines()[0]->tax()->code->value());
        self::assertCount(1, $this->app->make(ListPurchaseInvoices::class)->execute($this->admin()));
        self::assertSame(0, DB::table('purchase_posting_configurations')->count());
        self::assertSame(FinalizePurchaseInvoiceResult::Success, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), $result->id, new UserId(new Uuid(self::USER))));
        self::assertSame(FinalizePurchaseInvoiceResult::AlreadyFinalized, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), $result->id, new UserId(new Uuid(self::USER))));
        $final = $this->app->make(GetPurchaseInvoice::class)->execute($this->admin(), $result->id);
        self::assertSame(PurchaseInvoiceStatus::Finalized, $final?->status());
        self::assertSame(self::USER, $final?->finalizedBy()?->toString());
        self::assertSame('2026-08-25 14:30:00', $final?->finalizedAt()?->format('Y-m-d H:i:s'));
        $this->assertNoFinancialSideEffects();
    }

    public function test_duplicate_case_sensitive_identity_and_draft_update_guard(): void
    {
        $create = $this->app->make(CreatePurchaseInvoice::class);
        $first = $create->execute($this->admin(), $this->input('Case-1'));
        self::assertSame(CreatePurchaseInvoiceStatus::DuplicateSupplierInvoice, $create->execute($this->admin(), $this->input(' Case-1 '))->status);
        self::assertSame(CreatePurchaseInvoiceStatus::Success, $create->execute($this->admin(), $this->input('case-1'))->status);
        self::assertSame(2, DB::table('purchase_invoices')->count());
        self::assertSame(2, DB::table('purchase_invoice_lines')->count());
        self::assertSame(UpdateDraftPurchaseInvoiceResult::DuplicateSupplierInvoice, $this->app->make(UpdateDraftPurchaseInvoice::class)->execute($this->admin(), $first->id, $this->input('case-1')));
        self::assertSame('Case-1', DB::table('purchase_invoices')->where('id', $first->id->toString())->value('supplier_invoice_number'));
        self::assertSame(1, DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $first->id->toString())->count());
    }

    public function test_supplier_and_masterdata_changes_do_not_reinterpret_snapshot_and_later_deactivation_does_not_block_finalize(): void
    {
        $result = $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $this->input('HIST-1'));
        DB::table('relations')->where('id', '91000000-0000-4000-8000-000000000002')->update(['display_name' => 'Changed', 'vat_identification_number' => null]);
        DB::table('suppliers')->where('id', self::SUPPLIER)->update(['active' => false]);
        DB::table('ledger_accounts')->where('id', self::ACCOUNT)->update(['name' => 'Changed account']);
        DB::table('tax_codes')->where('id', self::TAX)->update(['name' => 'Changed tax', 'rate' => '9', 'treatment' => 'domestic_reduced', 'vat_return_classification' => 'domestic_reduced']);
        self::assertSame(FinalizePurchaseInvoiceResult::Success, $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), $result->id, new UserId(new Uuid(self::USER))));
        $invoice = $this->app->make(GetPurchaseInvoice::class)->execute($this->admin(), $result->id);
        self::assertSame('Supplier Original', $invoice?->supplierSnapshot()->name->value());
        self::assertSame('Expense Original', $invoice?->lines()[0]->account()->name->value());
        self::assertSame('Input 21', $invoice?->lines()[0]->tax()->name->value());
        self::assertSame('21', $invoice?->lines()[0]->tax()->rate->value());
    }

    public function test_supplier_line_and_tenant_validation_are_typed_and_non_leaking(): void
    {
        $input = $this->input('INVALID');
        self::assertSame(CreatePurchaseInvoiceStatus::SupplierNotFound, $this->app->make(CreatePurchaseInvoice::class)->execute(new AdministrationId(new Uuid(self::B)), $input)->status);
        DB::table('suppliers')->where('id', self::SUPPLIER)->update(['active' => false]);
        self::assertSame(CreatePurchaseInvoiceStatus::InvalidSupplier, $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $input)->status);
        DB::table('suppliers')->where('id', self::SUPPLIER)->update(['active' => true]);
        DB::table('tax_codes')->where('id', self::TAX)->update(['direction' => 'output']);
        self::assertSame(CreatePurchaseInvoiceStatus::InvalidLineReference, $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $input)->status);
        self::assertSame(0, DB::table('purchase_invoices')->count());
    }

    public function test_cancel_is_durable_does_not_release_duplicate_identity_and_finalized_mutation_is_blocked(): void
    {
        $create = $this->app->make(CreatePurchaseInvoice::class);
        $result = $create->execute($this->admin(), $this->input('CANCEL-1'));
        self::assertSame(CancelPurchaseInvoiceResult::Success, $this->app->make(CancelPurchaseInvoice::class)->execute($this->admin(), $result->id));
        self::assertSame(CancelPurchaseInvoiceResult::AlreadyCancelled, $this->app->make(CancelPurchaseInvoice::class)->execute($this->admin(), $result->id));
        self::assertSame(CreatePurchaseInvoiceStatus::DuplicateSupplierInvoice, $create->execute($this->admin(), $this->input('CANCEL-1'))->status);
        self::assertSame(PurchaseInvoiceStatus::Cancelled, $this->app->make(GetPurchaseInvoice::class)->execute($this->admin(), $result->id)?->status());
    }

    public function test_real_mysql_concurrent_duplicate_create_has_one_complete_winner(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the purchase invoice concurrency test.');
        }
        DB::commit();
        $files = $this->forkResults('purchase-create-', function (int $index): string {
            return $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $this->input('RACE-CREATE'))->status->name;
        });
        $results = $this->readForkResults($files);
        sort($results);
        self::assertSame(['DuplicateSupplierInvoice', 'Success'], $results);
        $invoiceId = DB::table('purchase_invoices')->where('supplier_invoice_number', 'RACE-CREATE')->value('id');
        self::assertNotNull($invoiceId);
        self::assertSame(1, DB::table('purchase_invoices')->where('supplier_invoice_number', 'RACE-CREATE')->count());
        self::assertSame(1, DB::table('purchase_invoice_lines')->where('purchase_invoice_id', $invoiceId)->count());
        $this->cleanupCommittedFixtures();
        DB::beginTransaction();
    }

    public function test_real_mysql_concurrent_finalize_preserves_the_single_winning_actor_and_time(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the purchase invoice concurrency test.');
        }
        $created = $this->app->make(CreatePurchaseInvoice::class)->execute($this->admin(), $this->input('RACE-FINALIZE'));
        self::assertNotNull($created->id);
        DB::commit();
        $actors = [self::USER, self::USER_B];
        $files = $this->forkResults('purchase-finalize-', function (int $index) use ($actors, $created): string {
            $actor = $actors[$index];
            $result = $this->app->make(FinalizePurchaseInvoice::class)->execute($this->admin(), $created->id, new UserId(new Uuid($actor)));

            return $result->name.':'.$actor;
        });
        $results = $this->readForkResults($files);
        $statuses = array_map(static fn (string $result): string => explode(':', $result, 2)[0], $results);
        sort($statuses);
        self::assertSame(['AlreadyFinalized', 'Success'], $statuses);
        $success = $results[array_search('Success', array_map(static fn (string $result): string => explode(':', $result, 2)[0], $results), true)];
        $winningActor = explode(':', $success, 2)[1];
        $row = DB::table('purchase_invoices')->where('id', $created->id->toString())->first();
        self::assertSame('finalized', $row?->status);
        self::assertSame($winningActor, $row?->finalized_by);
        self::assertSame('2026-08-25 14:30:00', (string) $row?->finalized_at);
        $this->cleanupCommittedFixtures();
        DB::beginTransaction();
    }

    private function input(string $number): PurchaseInvoiceDraftInput
    {
        return new PurchaseInvoiceDraftInput(new SupplierId(new Uuid(self::SUPPLIER)), new SupplierInvoiceNumber($number), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), null, new DateTimeImmutable('2026-09-20'), new Currency('EUR'), new PurchaseDocumentAddress(new AddressLine('Document Street 1'), null, new PostalCode('1000AA'), new City('Amsterdam'), new CountryCode('NL')), [new PurchaseInvoiceLineInput(new LineDescription('Office services'), new Quantity('1'), new Money('100', new Currency('EUR')), new LedgerAccountId(new Uuid(self::ACCOUNT)), new TaxCodeId(new Uuid(self::TAX)), true)]);
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::A));
    }

    private function assertNoFinancialSideEffects(): void
    {
        self::assertSame(0, DB::table('journal_entries')->count());
        self::assertSame(0, DB::table('journal_entry_lines')->count());
        self::assertSame(0, DB::table('tax_postings')->count());
        self::assertSame(0, DB::table('open_items')->count());
        self::assertSame(0, DB::table('purchase_invoice_postings')->count());
    }

    private function fixtures(): void
    {
        $now = now();
        foreach ([[self::A, 'P3A'], [self::B, 'P3B']] as [$id,$code]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }DB::table('domain_users')->insert([
            ['id' => self::USER, 'display_name' => 'Actor', 'email' => 'actor@example.com', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::USER_B, 'display_name' => 'Actor B', 'email' => 'actor-b@example.com', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('relations')->insert(['id' => '91000000-0000-4000-8000-000000000002', 'administration_id' => self::A, 'code' => 'SUPREL', 'display_name' => 'Supplier Original', 'vat_identification_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'NL', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('suppliers')->insert(['id' => self::SUPPLIER, 'administration_id' => self::A, 'relation_id' => '91000000-0000-4000-8000-000000000002', 'supplier_number' => 'S000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('ledger_accounts')->insert(['id' => self::ACCOUNT, 'administration_id' => self::A, 'code' => '4000', 'name' => 'Expense Original', 'type' => 'expense', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_codes')->insert(['id' => self::TAX, 'administration_id' => self::A, 'code' => 'INBTW21', 'name' => 'Input 21', 'rate' => '21', 'direction' => 'input', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now]);
    }

    /** @return list<string> */
    private function forkResults(string $prefix, callable $operation): array
    {
        $files = [tempnam(sys_get_temp_dir(), $prefix), tempnam(sys_get_temp_dir(), $prefix)];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $operation($index));
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        return $files;
    }

    /** @param list<string> $files
     * @return list<string>
     */
    private function readForkResults(array $files): array
    {
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $results;
    }

    private function cleanupCommittedFixtures(): void
    {
        DB::table('purchase_invoice_lines')->where('administration_id', self::A)->delete();
        DB::table('purchase_invoices')->where('administration_id', self::A)->delete();
        DB::table('tax_codes')->where('administration_id', self::A)->delete();
        DB::table('ledger_accounts')->where('administration_id', self::A)->delete();
        DB::table('suppliers')->where('administration_id', self::A)->delete();
        DB::table('relations')->where('administration_id', self::A)->delete();
        DB::table('domain_users')->whereIn('id', [self::USER, self::USER_B])->delete();
        DB::table('administrations')->whereIn('id', [self::A, self::B])->delete();
    }
}
