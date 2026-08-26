<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemStore;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Purchasing\CancelPurchaseInvoice;
use App\Application\Purchasing\CreatePurchaseInvoice;
use App\Application\Purchasing\FinalizePurchaseInvoice;
use App\Application\Purchasing\PostPurchaseInvoice;
use App\Application\Purchasing\PostPurchaseInvoiceResult;
use App\Application\Purchasing\PostPurchaseInvoiceStatus;
use App\Application\Purchasing\PurchaseInvoiceDraftInput;
use App\Application\Purchasing\PurchaseInvoiceLineInput;
use App\Application\Purchasing\PurchaseInvoicePosting;
use App\Application\Purchasing\PurchaseInvoicePostingRepository;
use App\Application\Purchasing\PurchaseInvoiceRepository;
use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
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
            self::assertSame(PostPurchaseInvoiceStatus::ConfigurationInvalid, $this->postInvoice($invoiceId)->status);
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

    private function postInvoice(string $id): PostPurchaseInvoiceResult
    {
        return $this->app->make(PostPurchaseInvoice::class)->execute($this->admin(), new PurchaseInvoiceId(new Uuid($id)), new PostingDate(new DateTimeImmutable('2026-08-25')));
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

    private function fixtures(): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => self::ADMIN, 'code' => 'P3POST', 'name' => 'P3 Post', 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Actor', 'email' => 'post@example.com', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => self::RELATION, 'administration_id' => self::ADMIN, 'code' => 'SUP', 'display_name' => 'Supplier', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('suppliers')->insert(['id' => self::SUPPLIER, 'administration_id' => self::ADMIN, 'relation_id' => self::RELATION, 'supplier_number' => 'S000001', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::EXPENSE, '4000', 'Expense', 'expense'], [self::ASSET, '0200', 'Asset', 'asset'], [self::AP, '1600', 'Accounts payable', 'liability'], [self::OTHER_AP, '1601', 'Other accounts payable', 'liability'], [self::VAT, '1520', 'Input VAT', 'asset']] as [$id, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $id, 'administration_id' => self::ADMIN, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => self::JOURNAL, 'administration_id' => self::ADMIN, 'code' => 'INK', 'name' => 'Purchase', 'type' => 'purchase', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_codes')->insert([
            ['id' => self::TAX21, 'administration_id' => self::ADMIN, 'code' => 'INBTW21', 'name' => 'Input 21', 'rate' => '21', 'direction' => 'input', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::TAX0, 'administration_id' => self::ADMIN, 'code' => 'INBTW0', 'name' => 'Input 0', 'rate' => '0', 'direction' => 'input', 'status' => 'active', 'treatment' => 'zero_rated', 'vat_return_classification' => 'domestic_zero_rated', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('purchase_posting_configurations')->insert(['administration_id' => self::ADMIN, 'purchase_journal_id' => self::JOURNAL, 'accounts_payable_ledger_account_id' => self::AP, 'input_vat_ledger_account_id' => self::VAT, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function cleanup(): void
    {
        foreach (['purchase_invoice_postings', 'open_items', 'tax_postings', 'journal_entry_lines', 'journal_entries', 'purchase_invoice_lines', 'purchase_invoices', 'purchase_posting_configurations', 'tax_codes', 'journals', 'ledger_accounts', 'suppliers', 'relations'] as $table) {
            DB::table($table)->where('administration_id', self::ADMIN)->delete();
        }
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
