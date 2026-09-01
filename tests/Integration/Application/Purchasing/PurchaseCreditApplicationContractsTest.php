<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Accounting\OpenItemMatchAppendResult;
use App\Application\Accounting\OpenItemMatchPair;
use App\Application\Accounting\OpenItemMatchRepository;
use App\Application\Banking\BankTransactionAllocationInput;
use App\Application\Banking\BankTransactionResult;
use App\Application\Banking\CreateManualBankTransaction;
use App\Application\Banking\FinalizeBankTransaction;
use App\Application\Banking\PostBankTransaction;
use App\Application\Purchasing\CancelPurchaseCreditInvoice;
use App\Application\Purchasing\CreatePurchaseCreditInvoice;
use App\Application\Purchasing\FinalizePurchaseCreditInvoice;
use App\Application\Purchasing\GetPurchaseCreditInvoice;
use App\Application\Purchasing\PostPurchaseCreditInvoice;
use App\Application\Purchasing\PostPurchaseCreditInvoiceResult;
use App\Application\Purchasing\PostPurchaseCreditInvoiceStatus;
use App\Application\Purchasing\PurchaseCreditClock;
use App\Application\Purchasing\PurchaseCreditDraftInput;
use App\Application\Purchasing\PurchaseCreditMutationResult;
use App\Application\Purchasing\PurchaseCreditPostingRepository;
use App\Application\Purchasing\UpdateDraftPurchaseCreditInvoice;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemMatch;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private const BANK_ACCOUNT = 'a1000000-0000-4000-8000-000000000018';

    private const BANK_JOURNAL = 'a1000000-0000-4000-8000-000000000019';

    private const BANK_LEDGER = 'a1000000-0000-4000-8000-000000000020';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
        $this->createOpenAccountingPeriodFixture(self::A);
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

    public function test_finalized_credit_posts_historical_reversal_atomically_and_is_idempotent(): void
    {
        $created = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('PCR-POST'), $this->actor());
        self::assertSame(PurchaseCreditMutationResult::Success, $created->status);
        self::assertSame(PurchaseCreditMutationResult::Success, $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor()));

        DB::table('journals')->where('id', self::JOURNAL)->update(['status' => 'inactive']);
        DB::table('ledger_accounts')->whereIn('id', [self::EXPENSE, self::VAT, self::AP])->update(['status' => 'inactive']);
        $beforeMatches = DB::table('open_item_matches')->count();
        $beforeSettlements = DB::table('open_item_settlements')->count();
        $postingDate = new PostingDate(new DateTimeImmutable('2026-08-27'));
        $post = $this->app->make(PostPurchaseCreditInvoice::class);

        $result = $post->execute($this->admin(), $created->id, $postingDate, $this->actor());
        self::assertSame(PostPurchaseCreditInvoiceStatus::Success, $result->status);
        self::assertSame(PostPurchaseCreditInvoiceStatus::AlreadyPosted, $post->execute($this->admin(), $created->id, $postingDate, $this->actor())->status);
        self::assertSame(1, DB::table('purchase_credit_invoice_postings')->where('purchase_credit_invoice_id', $created->id->toString())->count());
        self::assertSame(1, DB::table('purchase_credit_source_line_claims')->where('purchase_credit_invoice_id', $created->id->toString())->count());
        self::assertSame('posted', DB::table('purchase_credit_invoices')->where('id', $created->id->toString())->value('status'));
        self::assertSame(self::USER, DB::table('purchase_credit_invoices')->where('id', $created->id->toString())->value('posted_by'));
        self::assertSame(self::JOURNAL, DB::table('journal_entries')->where('id', $result->journalEntryId?->toString())->value('journal_id'));

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $result->journalEntryId?->toString())->get()->keyBy('ledger_account_id');
        self::assertSame('100', (string) $lines[self::EXPENSE]->credit_amount);
        self::assertSame('21', (string) $lines[self::VAT]->credit_amount);
        self::assertSame('121', (string) $lines[self::AP]->debit_amount);
        $tax = DB::table('tax_postings')->where('source_document_type', 'purchase_credit_invoice')->first();
        self::assertSame(self::TAX_POSTING, $tax?->reversed_tax_posting_id);
        self::assertSame('2026-08-22', $tax?->posting_date);
        $open = DB::table('open_items')->where('id', $result->openItemId?->toString())->first();
        self::assertSame('payable', $open?->open_item_type);
        self::assertSame('debit', $open?->side);
        self::assertSame('121', (string) $open?->original_amount);
        self::assertNull($open?->due_date);
        self::assertSame(self::AP, $open?->control_ledger_account_id);
        self::assertSame(self::RELATION, $open?->relation_id);
        $readModel = $this->app->make(PurchaseCreditPostingRepository::class)->findReadModel($this->admin(), $created->id);
        self::assertSame(self::INVOICE, $readModel?->sourceInvoiceId->toString());
        self::assertSame(self::OPEN_ITEM, $readModel?->sourcePayableOpenItemId->toString());
        self::assertSame('121', $readModel?->grossAmount->amount());
        self::assertTrue($readModel?->allSourceLinesClaimed);
        self::assertCount(1, $readModel?->reversalTaxPostingIdsByCreditLine ?? []);
        self::assertSame($beforeMatches + 1, DB::table('open_item_matches')->count());
        self::assertSame($beforeSettlements, DB::table('open_item_settlements')->count());
        self::assertSame('121', $result->matchedAmount?->amount());
        self::assertSame('0', $result->sourceRemainingAmount?->amount());
        self::assertSame('0', $result->creditRemainingAmount?->amount());
    }

    public function test_purchase_credit_period_denials_are_typed_and_side_effect_free(): void
    {
        $created = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('PCR-PERIOD'), $this->actor());
        self::assertSame(PurchaseCreditMutationResult::Success, $created->status);
        self::assertSame(PurchaseCreditMutationResult::Success, $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor()));
        $post = $this->app->make(PostPurchaseCreditInvoice::class);
        $date = new PostingDate(new DateTimeImmutable('2026-08-27'));
        $before = [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(), DB::table('open_item_matches')->count()];
        DB::table('accounting_periods')->where('administration_id', self::A)->update(['status' => 'closed']);
        self::assertSame(PostPurchaseCreditInvoiceStatus::PeriodClosed, $post->execute($this->admin(), $created->id, $date, $this->actor())->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(), DB::table('open_item_matches')->count()]);
        DB::table('accounting_periods')->where('administration_id', self::A)->delete();
        self::assertSame(PostPurchaseCreditInvoiceStatus::NoAccountingPeriod, $post->execute($this->admin(), $created->id, $date, $this->actor())->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('tax_postings')->count(), DB::table('open_items')->count(), DB::table('open_item_matches')->count()]);
    }

    public function test_partially_paid_source_matches_only_current_open_amount(): void
    {
        $this->sourceSettlement('a1000000-0000-4000-8000-000000000091', '40');
        $result = $this->postCredit('PCR-PARTIAL');

        self::assertSame(PostPurchaseCreditInvoiceStatus::Success, $result->status);
        self::assertSame('81', $result->matchedAmount?->amount());
        self::assertSame('0', $result->sourceRemainingAmount?->amount());
        self::assertSame('40', $result->creditRemainingAmount?->amount());
        self::assertSame(1, DB::table('open_item_settlements')->where('open_item_id', self::OPEN_ITEM)->count());
        self::assertSame('81', (string) DB::table('open_item_matches')->value('amount'));
    }

    public function test_current_open_balance_includes_a_durable_later_dated_settlement(): void
    {
        $this->sourceSettlement('a1000000-0000-4000-8000-000000000095', '40', '2026-08-28');
        $result = $this->postCredit('PCR-LATER-SETTLEMENT');

        self::assertSame(PostPurchaseCreditInvoiceStatus::Success, $result->status);
        self::assertSame('81', $result->matchedAmount?->amount());
        self::assertSame('0', $result->sourceRemainingAmount?->amount());
        self::assertSame('40', $result->creditRemainingAmount?->amount());
        self::assertSame('81', (string) DB::table('open_item_matches')->value('amount'));
    }

    public function test_fully_paid_source_creates_no_match_and_leaves_supplier_credit_balance(): void
    {
        $this->sourceSettlement('a1000000-0000-4000-8000-000000000092', '121');
        $result = $this->postCredit('PCR-PAID');

        self::assertSame(PostPurchaseCreditInvoiceStatus::Success, $result->status);
        self::assertSame('0', $result->matchedAmount?->amount());
        self::assertSame('0', $result->sourceRemainingAmount?->amount());
        self::assertSame('121', $result->creditRemainingAmount?->amount());
        self::assertSame(0, DB::table('open_item_matches')->count());
        self::assertSame(1, DB::table('open_item_settlements')->where('open_item_id', self::OPEN_ITEM)->count());
    }

    public function test_match_failure_rolls_back_the_complete_purchase_credit_post(): void
    {
        $created = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('PCR-MATCH-FAIL'), $this->actor());
        $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor());
        $real = $this->app->make(OpenItemMatchRepository::class);
        $this->app->instance(OpenItemMatchRepository::class, new class($real) implements OpenItemMatchRepository
        {
            public function __construct(private OpenItemMatchRepository $inner) {}

            public function findLocked(AdministrationId $administrationId, OpenItemId $openItemId): ?OpenItem
            {
                return $this->inner->findLocked($administrationId, $openItemId);
            }

            public function findLockedPair(AdministrationId $administrationId, OpenItemId $debitOpenItemId, OpenItemId $creditOpenItemId): ?OpenItemMatchPair
            {
                return $this->inner->findLockedPair($administrationId, $debitOpenItemId, $creditOpenItemId);
            }

            public function appendMatch(OpenItemMatch $match): OpenItemMatchAppendResult
            {
                throw new \RuntimeException('Forced match failure.');
            }
        });

        $result = $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->actor());
        self::assertSame(PostPurchaseCreditInvoiceStatus::PostingFailure, $result->status);
        self::assertSame('finalized', DB::table('purchase_credit_invoices')->where('id', $created->id->toString())->value('status'));
        self::assertSame(0, DB::table('purchase_credit_invoice_postings')->count());
        self::assertSame(0, DB::table('purchase_credit_source_line_claims')->count());
        self::assertSame(0, DB::table('tax_postings')->where('type', 'reversal')->count());
        self::assertSame(0, DB::table('open_items')->where('side', 'debit')->count());
        self::assertSame(0, DB::table('open_item_matches')->count());
    }

    #[DataProvider('paymentRaceAmounts')]
    public function test_real_mysql_supplier_payment_and_credit_matching_never_over_apply_source(string $paymentAmount): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for PurchaseCredit concurrency tests.');
        }
        $credit = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('PCR-PAY-RACE-'.$paymentAmount), $this->actor());
        $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $credit->id, $this->actor());
        [$created, $bankId] = $this->app->make(CreateManualBankTransaction::class)->execute($this->admin(), new AdministrationBankAccountId(new Uuid(self::BANK_ACCOUNT)), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('-'.$paymentAmount, new Currency('EUR')), new BankTransactionReference('PAY-RACE-'.$paymentAmount), new TransactionDescription('Supplier payment race'), new RelationId(new Uuid(self::RELATION)), $this->actor(), [new BankTransactionAllocationInput(new PaymentAllocationId(new Uuid($paymentAmount === '121' ? 'a1000000-0000-4000-8000-000000000093' : 'a1000000-0000-4000-8000-000000000094')), new OpenItemId(new Uuid(self::OPEN_ITEM)), new Money($paymentAmount, new Currency('EUR')))]);
        self::assertSame(BankTransactionResult::Success, $created);
        self::assertSame(BankTransactionResult::Success, $this->app->make(FinalizeBankTransaction::class)->execute($this->admin(), $bankId, $this->actor()));

        DB::commit();
        $results = $this->forkResults('pc-payment-race-', function (int $index) use ($credit, $bankId): string {
            if ($index === 0) {
                return 'credit:'.$this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $credit->id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->actor())->status->name;
            }

            return 'payment:'.$this->app->make(PostBankTransaction::class)->execute($this->admin(), $bankId, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->actor())->name;
        });
        self::assertContains('credit:Success', $results);
        self::assertTrue(in_array('payment:Success', $results, true) || in_array('payment:AllocationExceedsOpenBalance', $results, true));
        $settled = (string) DB::table('open_item_settlements')->where('open_item_id', self::OPEN_ITEM)->sum('amount');
        $matched = (string) DB::table('open_item_matches')->where('credit_open_item_id', self::OPEN_ITEM)->sum('amount');
        self::assertLessThanOrEqual(0, bccomp(bcadd($settled, $matched, 4), '121', 4));
        self::assertSame(0, DB::table('open_items')->where('id', self::OPEN_ITEM)->whereRaw('original_amount < 0')->count());
        $this->cleanupCommittedFixtures();
    }

    public static function paymentRaceAmounts(): array
    {
        return [['121'], ['40']];
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

        $double = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Race-post'), $this->actor());
        $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $double->id, $this->actor());
        $postResults = $this->forkResults('pc-post-', fn (): string => $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $double->id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->actor())->status->name);
        sort($postResults);
        self::assertSame(['AlreadyPosted', 'Success'], $postResults);
        self::assertSame(1, DB::table('purchase_credit_invoice_postings')->where('purchase_credit_invoice_id', $double->id->toString())->count());
        $doubleLinkage = DB::table('purchase_credit_invoice_postings')->where('purchase_credit_invoice_id', $double->id->toString())->first();
        DB::table('purchase_credit_source_line_claims')->where('purchase_credit_invoice_id', $double->id->toString())->delete();
        DB::table('purchase_credit_invoice_postings')->where('purchase_credit_invoice_id', $double->id->toString())->delete();
        DB::table('tax_postings')->where('source_document_id', $double->id->toString())->delete();
        DB::table('open_item_matches')->where('debit_open_item_id', $doubleLinkage->open_item_id)->delete();
        DB::table('open_items')->where('id', $doubleLinkage->open_item_id)->delete();
        DB::table('journal_entry_lines')->where('journal_entry_id', $doubleLinkage->journal_entry_id)->delete();
        DB::table('journal_entries')->where('id', $doubleLinkage->journal_entry_id)->delete();
        DB::table('purchase_credit_invoice_lines')->where('purchase_credit_invoice_id', $double->id->toString())->delete();
        DB::table('purchase_credit_invoices')->where('id', $double->id->toString())->delete();

        $winner = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Race-claim-a'), $this->actor());
        $loser = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input('Race-claim-b'), $this->actor());
        $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $winner->id, $this->actor());
        $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $loser->id, $this->actor());
        $claimResults = $this->forkResults('pc-claim-', function (int $index) use ($winner, $loser): string {
            $id = $index === 0 ? $winner->id : $loser->id;

            return $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->actor())->status->name;
        });
        sort($claimResults);
        self::assertSame(['SourceLineAlreadyCredited', 'Success'], $claimResults);
        self::assertSame(1, DB::table('purchase_credit_source_line_claims')->where('source_purchase_invoice_line_id', self::LINE)->count());
        self::assertSame(1, DB::table('purchase_credit_invoices')->whereIn('id', [$winner->id->toString(), $loser->id->toString()])->where('status', 'finalized')->count());
        $this->cleanupCommittedFixtures();
        DB::beginTransaction();
    }

    private function input(string $number): PurchaseCreditDraftInput
    {
        return new PurchaseCreditDraftInput(new PurchaseInvoiceId(new Uuid(self::INVOICE)), new PurchaseCreditInvoiceNumber($number), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-22'), [new PurchaseInvoiceLineId(new Uuid(self::LINE))]);
    }

    private function postCredit(string $number): PostPurchaseCreditInvoiceResult
    {
        $created = $this->app->make(CreatePurchaseCreditInvoice::class)->execute($this->admin(), $this->input($number), $this->actor());
        $this->app->make(FinalizePurchaseCreditInvoice::class)->execute($this->admin(), $created->id, $this->actor());

        return $this->app->make(PostPurchaseCreditInvoice::class)->execute($this->admin(), $created->id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->actor());
    }

    private function sourceSettlement(string $id, string $amount, string $effectiveDate = '2026-08-20'): void
    {
        DB::table('open_item_settlements')->insert(['id' => $id, 'administration_id' => self::A, 'open_item_id' => self::OPEN_ITEM, 'payment_allocation_id' => null, 'effective_date' => $effectiveDate, 'amount' => $amount, 'currency' => 'EUR', 'source_journal_entry_id' => self::ENTRY, 'type' => 'applied', 'reversed_settlement_id' => null, 'created_at' => now(), 'updated_at' => now()]);
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
        DB::table('open_item_matches')->where('administration_id', self::A)->delete();
        DB::table('purchase_credit_source_line_claims')->where('administration_id', self::A)->delete();
        DB::table('purchase_credit_invoice_postings')->where('administration_id', self::A)->delete();
        DB::table('purchase_credit_invoice_lines')->where('administration_id', self::A)->delete();
        DB::table('purchase_credit_invoices')->where('administration_id', self::A)->delete();
        DB::table('purchase_invoice_postings')->where('administration_id', self::A)->delete();
        DB::table('bank_transaction_postings')->where('administration_id', self::A)->delete();
        DB::table('open_item_settlements')->where('administration_id', self::A)->delete();
        DB::table('payment_allocations')->where('administration_id', self::A)->delete();
        DB::table('payments')->where('administration_id', self::A)->delete();
        DB::table('bank_transactions')->where('administration_id', self::A)->delete();
        DB::table('banking_posting_configurations')->where('administration_id', self::A)->delete();
        DB::table('open_items')->where('administration_id', self::A)->delete();
        DB::table('tax_postings')->where('administration_id', self::A)->where('type', 'reversal')->delete();
        DB::table('tax_postings')->where('administration_id', self::A)->delete();
        DB::table('journal_entry_lines')->where('administration_id', self::A)->delete();
        DB::table('journal_entries')->where('administration_id', self::A)->delete();
        DB::table('purchase_invoice_lines')->where('administration_id', self::A)->delete();
        DB::table('purchase_invoices')->where('administration_id', self::A)->delete();
        DB::table('administration_bank_accounts')->where('administration_id', self::A)->delete();
        DB::table('journals')->where('administration_id', self::A)->delete();
        DB::table('tax_codes')->where('administration_id', self::A)->delete();
        DB::table('ledger_accounts')->where('administration_id', self::A)->delete();
        DB::table('suppliers')->where('administration_id', self::A)->delete();
        DB::table('relations')->where('administration_id', self::A)->delete();
        DB::table('accounting_periods')->where('administration_id', self::A)->delete();
        DB::table('book_years')->where('administration_id', self::A)->delete();
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
        DB::table('journals')->insert(['id' => self::BANK_JOURNAL, 'administration_id' => self::A, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'bank', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('ledger_accounts')->insert(['id' => self::BANK_LEDGER, 'administration_id' => self::A, 'code' => '1100', 'name' => 'Bank', 'type' => 'asset', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('administration_bank_accounts')->insert(['id' => self::BANK_ACCOUNT, 'administration_id' => self::A, 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_holder' => 'PC', 'label' => 'Main', 'currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('banking_posting_configurations')->insert(['administration_id' => self::A, 'administration_bank_account_id' => self::BANK_ACCOUNT, 'bank_journal_id' => self::BANK_JOURNAL, 'bank_ledger_account_id' => self::BANK_LEDGER, 'created_at' => $now, 'updated_at' => $now]);
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
