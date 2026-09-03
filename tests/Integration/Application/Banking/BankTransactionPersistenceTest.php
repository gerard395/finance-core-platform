<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\MatchOpenItems;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Banking\BankTransactionAllocationInput;
use App\Application\Banking\BankTransactionPostingRepository;
use App\Application\Banking\BankTransactionRepository;
use App\Application\Banking\BankTransactionResult;
use App\Application\Banking\BankTransactionReversalRepository;
use App\Application\Banking\BankTransactionSettlementReversalLinkRepository;
use App\Application\Banking\CancelBankTransaction;
use App\Application\Banking\CreateAndPostOtherBankTransaction;
use App\Application\Banking\CreateManualBankTransaction;
use App\Application\Banking\FinalizeBankTransaction;
use App\Application\Banking\GetBankTransactionPostingDetail;
use App\Application\Banking\PostBankTransaction;
use App\Application\Banking\PostBankTransactionStatus;
use App\Application\Banking\PostOtherBankTransactionStatus;
use App\Application\Banking\ReverseBankTransaction;
use App\Application\Banking\ReverseBankTransactionStatus;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class BankTransactionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'b2700000-0000-4000-8000-000000000001';

    private const B = 'b2700000-0000-4000-8000-000000000002';

    private const USER = 'b2700000-0000-4000-8000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Actor', 'email' => 'actor@bank.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[self::A, 'A'], [self::B, 'B']] as [$id,$code]) {
            DB::table('administrations')->insert(['id' => $id, 'code' => 'BT'.$code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('relations')->insert(['id' => $this->relation($id)->toString(), 'administration_id' => $id, 'code' => 'R'.$code, 'display_name' => 'Relation '.$code, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('ledger_accounts')->insert(['id' => $this->ledger($id), 'administration_id' => $id, 'code' => '1300', 'name' => 'Control', 'type' => $id === self::A ? 'asset' : 'liability', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('journals')->insert(['id' => $this->journal($id), 'administration_id' => $id, 'code' => 'OPEN', 'name' => 'Opening', 'type' => 'general', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('journal_entries')->insert(['id' => $this->entry($id), 'administration_id' => $id, 'journal_id' => $this->journal($id), 'posting_date' => '2026-08-01', 'reference' => 'E'.$code, 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('administration_bank_accounts')->insert(['id' => $this->bank($id)->toString(), 'administration_id' => $id, 'iban' => $id === self::A ? 'NL91ABNA0417164300' : 'NL02ABNA0123456789', 'bic' => null, 'account_holder' => 'Holder', 'label' => 'Main', 'currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        } $this->openItem(self::A, 1, 'receivable', 'debit', '100');
        $this->createOpenAccountingPeriodFixture(self::A);
        $this->createOpenAccountingPeriodFixture(self::B);
        $this->openItem(self::A, 2, 'receivable', 'debit', '50');
        $this->openItem(self::B, 3, 'payable', 'credit', '100');
    }

    public function test_customer_receipt_roundtrip_multiple_allocations_finalize_snapshots_and_no_financial_side_effects(): void
    {
        $before = $this->financialCounts();
        [$result,$id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('150', new Currency('EUR')), new BankTransactionReference('REF'), new TransactionDescription('Receipt'), $this->relation(self::A), $this->user(), [$this->allocation(1, '100'), $this->allocation(2, '50')]);
        self::assertSame(BankTransactionResult::Success, $result);
        $draft = $this->repo()->find($this->admin(self::A), $id);
        self::assertSame(BankTransactionStatus::Draft, $draft?->status());
        self::assertSame(PaymentType::CustomerReceipt, $draft?->payment()->type());
        self::assertCount(2, $draft?->payment()->allocations() ?? []);
        self::assertNull($this->repo()->find($this->admin(self::B), $id));
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        $final = $this->repo()->find($this->admin(self::A), $id);
        self::assertSame(BankTransactionStatus::Finalized, $final?->status());
        self::assertNotNull($final?->finalizedBy());
        self::assertTrue($final?->payment()->allocations()[0]->isFinalized());
        self::assertSame($this->ledger(self::A), $final?->payment()->allocations()[0]->controlLedgerAccountId()?->toString());
        self::assertSame(BankTransactionResult::AlreadyFinalized, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        self::assertSame($before, $this->financialCounts());
    }

    public function test_supplier_direction_invalid_targets_exact_sum_cancel_and_missing_config_does_not_block_draft(): void
    {
        [$r,$id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('-100', new Currency('EUR')), new BankTransactionReference('PAY'), new TransactionDescription('Supplier'), $this->relation(self::A), $this->user(), [$this->allocation(1, '100')]);
        self::assertSame(BankTransactionResult::Success, $r);
        self::assertSame(PaymentType::SupplierPayment, $this->repo()->find($this->admin(self::A), $id)?->payment()->type());
        self::assertSame(BankTransactionResult::InvalidAllocation, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        self::assertSame(BankTransactionStatus::Draft, $this->repo()->find($this->admin(self::A), $id)?->status());
        self::assertSame(BankTransactionResult::Success, $this->app->make(CancelBankTransaction::class)->execute($this->admin(self::A), $id));
        self::assertSame(BankTransactionStatus::Cancelled, $this->repo()->find($this->admin(self::A), $id)?->status());
        self::assertSame(0, DB::table('banking_posting_configurations')->count());
    }

    public function test_cross_tenant_bank_relation_and_open_item_are_rejected(): void
    {
        [$r] = $this->create()->execute($this->admin(self::A), $this->bank(self::B), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('100', new Currency('EUR')), new BankTransactionReference('X'), new TransactionDescription('Cross'), $this->relation(self::A), $this->user());
        self::assertSame(BankTransactionResult::NotFound, $r);
        [$r] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('100', new Currency('EUR')), new BankTransactionReference('X'), new TransactionDescription('Cross'), $this->relation(self::B), $this->user());
        self::assertSame(BankTransactionResult::InvalidReference, $r);
    }

    public function test_finalized_receipt_requires_configuration_then_posts_once_and_settles_exactly(): void
    {
        [, $id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('100', new Currency('EUR')), new BankTransactionReference('POST-REC'), new TransactionDescription('Receipt'), $this->relation(self::A), $this->user(), [$this->allocation(1, '100')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        $postingDate = new PostingDate(new DateTimeImmutable('2026-08-27'));
        self::assertSame(PostBankTransactionStatus::ConfigurationMissing, $this->postBankTransaction()->execute($this->admin(self::A), $id, $postingDate, $this->user()));
        $this->configure(self::A);
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $id, $postingDate, $this->user()));
        self::assertSame(PostBankTransactionStatus::AlreadyPosted, $this->postBankTransaction()->execute($this->admin(self::A), $id, $postingDate, $this->user()));
        self::assertSame('posted', DB::table('bank_transactions')->where('id', $id->toString())->value('status'));
        self::assertSame(1, DB::table('bank_transaction_postings')->where('bank_transaction_id', $id->toString())->count());
        self::assertSame(1, DB::table('open_item_settlements')->where('payment_allocation_id', $this->allocation(1, '100')->id->toString())->count());
        self::assertSame(2, DB::table('journal_entry_lines')->where('journal_entry_id', DB::table('bank_transaction_postings')->where('bank_transaction_id', $id->toString())->value('journal_entry_id'))->count());
        $detail = $this->app->make(GetBankTransactionPostingDetail::class)->execute($this->admin(self::A), $id);
        self::assertSame('2026-08-27', $detail?->posting->postingDate->value()->format('Y-m-d'));
        self::assertSame(0, bccomp('100', (string) $detail?->settlements[0]->settlementAmount->amount(), 4));
        self::assertTrue($detail?->settlements[0]->remainingOpenAmount->isZero());
        self::assertNull($this->app->make(GetBankTransactionPostingDetail::class)->execute($this->admin(self::B), $id));
    }

    public function test_bank_post_period_denials_are_typed_and_side_effect_free(): void
    {
        [, $id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('100', new Currency('EUR')), new BankTransactionReference('PERIOD-POST'), new TransactionDescription('Period'), $this->relation(self::A), $this->user(), [$this->allocation(1, '100')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        $this->configure(self::A);
        $before = [DB::table('journal_entries')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_postings')->count()];
        DB::table('accounting_periods')->where('administration_id', self::A)->update(['status' => 'closed']);
        self::assertSame(PostBankTransactionStatus::PeriodClosed, $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_postings')->count()]);
        DB::table('accounting_periods')->where('administration_id', self::A)->delete();
        self::assertSame(PostBankTransactionStatus::NoAccountingPeriod, $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_postings')->count()]);
    }

    public function test_other_intent_posts_and_reverses_historical_journal_without_payment_or_settlement(): void
    {
        $this->configure(self::A);
        $contra = 'b27a0000-0000-4000-8000-000000000001';
        DB::table('ledger_accounts')->insert(['id' => $contra, 'administration_id' => self::A, 'code' => '4990', 'name' => 'Other', 'type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $postingDate = new PostingDate(new DateTimeImmutable('2026-08-27'));
        $result = $this->app->make(CreateAndPostOtherBankTransaction::class)->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contra)), new Money('100', new Currency('EUR')), $postingDate, new BankTransactionReference('OTHER-IN'), new TransactionDescription('Other incoming'), $this->user());

        self::assertSame(PostOtherBankTransactionStatus::Success, $result->status);
        self::assertNotNull($result->bankTransactionId);
        $transaction = $this->repo()->find($this->admin(self::A), $result->bankTransactionId);
        self::assertNull($transaction?->paymentOrNull());
        self::assertSame($contra, $transaction?->otherIntentOrNull()?->contraLedgerAccountId()->toString());
        self::assertSame(0, DB::table('payments')->where('bank_transaction_id', $result->bankTransactionId->toString())->count());
        self::assertSame(0, DB::table('open_item_settlements')->where('source_journal_entry_id', DB::table('bank_transaction_postings')->where('bank_transaction_id', $result->bankTransactionId->toString())->value('journal_entry_id'))->count());

        $entryId = DB::table('bank_transaction_postings')->where('bank_transaction_id', $result->bankTransactionId->toString())->value('journal_entry_id');
        $bankLine = DB::table('journal_entry_lines')->where('journal_entry_id', $entryId)->where('ledger_account_id', str_replace('b270', 'b279', self::A))->first();
        $contraLine = DB::table('journal_entry_lines')->where('journal_entry_id', $entryId)->where('ledger_account_id', $contra)->first();
        self::assertSame(0, bccomp('100', (string) $bankLine?->debit_amount, 4));
        self::assertSame(0, bccomp('100', (string) $contraLine?->credit_amount, 4));

        $reversal = $this->reverse()->execute($this->admin(self::A), $result->bankTransactionId, $postingDate, new BankTransactionReversalReason('Reverse other'), $this->user());
        self::assertSame(ReverseBankTransactionStatus::Success, $reversal->status);
        self::assertSame(0, $reversal->success?->reversedSettlementCount);
        self::assertSame(0, DB::table('bank_transaction_settlement_reversal_links')->where('bank_transaction_reversal_id', $reversal->success?->reversalId->toString())->count());
        $reversalLines = DB::table('journal_entry_lines')->where('journal_entry_id', $reversal->success?->reversalJournalEntryId->toString())->get()->keyBy('ledger_account_id');
        self::assertSame(0, bccomp('100', (string) $reversalLines[$contra]?->debit_amount, 4));
        self::assertSame(0, bccomp('100', (string) $reversalLines[str_replace('b270', 'b279', self::A)]?->credit_amount, 4));
        self::assertSame(ReverseBankTransactionStatus::AlreadyReversed, $this->reverse()->execute($this->admin(self::A), $result->bankTransactionId, $postingDate, new BankTransactionReversalReason('Again'), $this->user())->status);
        self::assertSame(ReverseBankTransactionStatus::NotFound, $this->reverse()->execute($this->admin(self::B), $result->bankTransactionId, $postingDate, new BankTransactionReversalReason('Cross tenant'), $this->user())->status);

        $outgoing = $this->app->make(CreateAndPostOtherBankTransaction::class)->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contra)), new Money('-40', new Currency('EUR')), $postingDate, new BankTransactionReference('OTHER-OUT'), new TransactionDescription('Other outgoing'), $this->user());
        self::assertSame(PostOtherBankTransactionStatus::Success, $outgoing->status);
        $outgoingEntry = DB::table('bank_transaction_postings')->where('bank_transaction_id', $outgoing->bankTransactionId?->toString())->value('journal_entry_id');
        $outgoingLines = DB::table('journal_entry_lines')->where('journal_entry_id', $outgoingEntry)->get()->keyBy('ledger_account_id');
        self::assertSame(0, bccomp('40', (string) $outgoingLines[$contra]?->debit_amount, 4));
        self::assertSame(0, bccomp('40', (string) $outgoingLines[str_replace('b270', 'b279', self::A)]?->credit_amount, 4));
        $outgoingReversal = $this->reverse()->execute($this->admin(self::A), $outgoing->bankTransactionId, $postingDate, new BankTransactionReversalReason('Reverse outgoing'), $this->user());
        self::assertSame(ReverseBankTransactionStatus::Success, $outgoingReversal->status);
        $outgoingReversalLines = DB::table('journal_entry_lines')->where('journal_entry_id', $outgoingReversal->success?->reversalJournalEntryId->toString())->get()->keyBy('ledger_account_id');
        self::assertSame(0, bccomp('40', (string) $outgoingReversalLines[str_replace('b270', 'b279', self::A)]?->debit_amount, 4));
        self::assertSame(0, bccomp('40', (string) $outgoingReversalLines[$contra]?->credit_amount, 4));
    }

    public function test_other_outgoing_and_typed_denials_are_side_effect_free(): void
    {
        $contraA = 'b27a0000-0000-4000-8000-000000000002';
        $contraB = 'b27a0000-0000-4000-8000-000000000003';
        foreach ([[self::A, $contraA], [self::B, $contraB]] as [$admin, $contra]) {
            DB::table('ledger_accounts')->insert(['id' => $contra, 'administration_id' => $admin, 'code' => '4991', 'name' => 'Other', 'type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $post = $this->app->make(CreateAndPostOtherBankTransaction::class);
        $date = new PostingDate(new DateTimeImmutable('2026-08-27'));
        $arguments = [$this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contraA)), new Money('-25', new Currency('EUR')), $date, new BankTransactionReference('OTHER-OUT'), new TransactionDescription('Other outgoing'), $this->user()];
        self::assertSame(PostOtherBankTransactionStatus::MissingPostingConfiguration, $post->execute(...$arguments)->status);
        self::assertSame(0, DB::table('bank_transactions')->count());

        $this->configure(self::A);
        self::assertSame(PostOtherBankTransactionStatus::InvalidContraAccount, $post->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contraB)), new Money('-25', new Currency('EUR')), $date, new BankTransactionReference('CROSS'), new TransactionDescription('Cross'), $this->user())->status);
        self::assertSame(PostOtherBankTransactionStatus::InvalidContraAccount, $post->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid(str_replace('b270', 'b279', self::A))), new Money('-25', new Currency('EUR')), $date, new BankTransactionReference('PROTECTED'), new TransactionDescription('Protected'), $this->user())->status);
        self::assertSame(PostOtherBankTransactionStatus::NotFound, $post->execute($this->admin(self::A), $this->bank(self::B), new LedgerAccountId(new Uuid($contraA)), new Money('-25', new Currency('EUR')), $date, new BankTransactionReference('BANK'), new TransactionDescription('Cross bank'), $this->user())->status);

        DB::table('accounting_periods')->where('administration_id', self::A)->update(['status' => 'closed']);
        self::assertSame(PostOtherBankTransactionStatus::PeriodClosed, $post->execute(...$arguments)->status);
        DB::table('accounting_periods')->where('administration_id', self::A)->delete();
        self::assertSame(PostOtherBankTransactionStatus::NoAccountingPeriod, $post->execute(...$arguments)->status);
        self::assertSame(0, DB::table('bank_transactions')->count());
        self::assertSame(0, DB::table('journal_entries')->where('journal_id', str_replace('b270', 'b278', self::A))->count());
    }

    public function test_other_posting_rolls_back_transaction_intent_and_journal_on_failure(): void
    {
        $this->configure(self::A);
        $contra = 'b27a0000-0000-4000-8000-000000000004';
        DB::table('ledger_accounts')->insert(['id' => $contra, 'administration_id' => self::A, 'code' => '4992', 'name' => 'Other', 'type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $journals = $this->createMock(JournalEntryStore::class);
        $journals->method('append')->willThrowException(new \RuntimeException('Forced journal failure.'));
        $this->app->instance(JournalEntryStore::class, $journals);

        $result = $this->app->make(CreateAndPostOtherBankTransaction::class)->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contra)), new Money('10', new Currency('EUR')), new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReference('ROLLBACK'), new TransactionDescription('Rollback'), $this->user());

        self::assertSame(PostOtherBankTransactionStatus::PostingFailure, $result->status);
        self::assertSame(0, DB::table('bank_transactions')->count());
        self::assertSame(0, DB::table('other_bank_transaction_intents')->count());
        self::assertSame(0, DB::table('bank_transaction_postings')->count());
    }

    public function test_real_mysql_double_other_post_has_one_coherent_financial_truth(): void
    {
        $this->configure(self::A);
        $contra = 'b27a0000-0000-4000-8000-000000000005';
        $transactionId = new BankTransactionId(new Uuid('b27a0000-0000-4000-8000-000000000006'));
        DB::table('ledger_accounts')->insert(['id' => $contra, 'administration_id' => self::A, 'code' => '4993', 'name' => 'Other', 'type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $operation = fn (): string => $this->app->make(CreateAndPostOtherBankTransaction::class)->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contra)), new Money('10', new Currency('EUR')), new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReference('CONCURRENT'), new TransactionDescription('Concurrent'), $this->user(), $transactionId)->status->name;

        $results = $this->runConcurrentBankOperations($operation, $operation);
        sort($results);

        self::assertSame(['AlreadyPosted', 'Success'], $results);
        self::assertSame(1, DB::table('bank_transactions')->where('id', $transactionId->toString())->count());
        self::assertSame(1, DB::table('other_bank_transaction_intents')->where('bank_transaction_id', $transactionId->toString())->count());
        self::assertSame(1, DB::table('bank_transaction_postings')->where('bank_transaction_id', $transactionId->toString())->count());
        $this->cleanupCommittedFixtures();
    }

    public function test_real_mysql_double_other_reversal_has_one_typed_winner_and_one_durable_contra(): void
    {
        $this->configure(self::A);
        $contra = 'b27a0000-0000-4000-8000-000000000007';
        DB::table('ledger_accounts')->insert(['id' => $contra, 'administration_id' => self::A, 'code' => '4994', 'name' => 'Other', 'type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $posted = $this->app->make(CreateAndPostOtherBankTransaction::class)->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contra)), new Money('30', new Currency('EUR')), new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReference('REV-RACE'), new TransactionDescription('Reversal race'), $this->user());
        self::assertSame(PostOtherBankTransactionStatus::Success, $posted->status);
        $transactionId = $posted->bankTransactionId;
        self::assertNotNull($transactionId);
        $operation = fn (): string => $this->reverse()->execute($this->admin(self::A), $transactionId, new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReversalReason('Concurrent reversal'), $this->user())->status->name;

        $results = $this->runConcurrentBankOperations($operation, $operation);
        sort($results);

        self::assertSame(['AlreadyReversed', 'Success'], $results);
        self::assertSame(1, DB::table('bank_transaction_reversals')->where('original_bank_transaction_id', $transactionId->toString())->count());
        self::assertSame(0, DB::table('bank_transaction_settlement_reversal_links')->count());
        self::assertSame('posted', DB::table('bank_transactions')->where('id', $transactionId->toString())->value('status'));
        self::assertSame(1, DB::table('journal_entries')->where('reference', 'REV-'.$transactionId->toString())->count());
        $entries = [DB::table('bank_transaction_postings')->where('bank_transaction_id', $transactionId->toString())->value('journal_entry_id'), DB::table('bank_transaction_reversals')->where('original_bank_transaction_id', $transactionId->toString())->value('reversal_journal_entry_id')];
        foreach ($entries as $entry) {
            self::assertSame(0, bccomp((string) DB::table('journal_entry_lines')->where('journal_entry_id', $entry)->sum('debit_amount'), (string) DB::table('journal_entry_lines')->where('journal_entry_id', $entry)->sum('credit_amount'), 4));
        }
        $this->cleanupCommittedFixtures();
    }

    public function test_failure_after_bank_transaction_row_rolls_back_before_other_intent(): void
    {
        $this->configure(self::A);
        $contra = 'b27a0000-0000-4000-8000-000000000008';
        $identity = new BankTransactionId(new Uuid('b27a0000-0000-4000-8000-000000000009'));
        DB::table('ledger_accounts')->insert(['id' => $contra, 'administration_id' => self::A, 'code' => '4995', 'name' => 'Other', 'type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $failing = $this->createMock(BankTransactionRepository::class);
        $failing->method('find')->willReturn(null);
        $failing->method('save')->willReturnCallback(static function (BankTransaction $transaction): void {
            DB::table('bank_transactions')->insert(['id' => $transaction->id()->toString(), 'administration_id' => $transaction->administrationId()->toString(), 'administration_bank_account_id' => $transaction->bankAccountId()->toString(), 'transaction_date' => $transaction->transactionDate()->value()->format('Y-m-d'), 'amount' => $transaction->amount()->amount(), 'currency' => 'EUR', 'reference' => $transaction->reference()->value(), 'description' => $transaction->description()->value(), 'status' => $transaction->status()->value, 'created_by' => $transaction->createdBy()->toString(), 'created_at' => $transaction->createdAt(), 'finalized_by' => $transaction->finalizedBy()?->toString(), 'finalized_at' => $transaction->finalizedAt(), 'posted_by' => null, 'posted_at' => null]);
            throw new \RuntimeException('Forced failure before Other intent persistence.');
        });
        $this->app->instance(BankTransactionRepository::class, $failing);

        $result = $this->app->make(CreateAndPostOtherBankTransaction::class)->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contra)), new Money('10', new Currency('EUR')), new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReference('ROW-FAIL'), new TransactionDescription('Row failure'), $this->user(), $identity);

        self::assertSame(PostOtherBankTransactionStatus::PostingFailure, $result->status);
        self::assertSame(0, DB::table('bank_transactions')->where('id', $identity->toString())->count());
        self::assertSame(0, DB::table('other_bank_transaction_intents')->where('bank_transaction_id', $identity->toString())->count());
        self::assertSame(0, DB::table('bank_transaction_postings')->where('bank_transaction_id', $identity->toString())->count());
        self::assertSame(0, DB::table('journal_entries')->where('reference', 'ROW-FAIL')->count());
    }

    public function test_corrupt_both_and_no_intent_states_fail_closed_for_repository_and_b3(): void
    {
        $this->configure(self::A);
        $contra = 'b27a0000-0000-4000-8000-000000000010';
        DB::table('ledger_accounts')->insert(['id' => $contra, 'administration_id' => self::A, 'code' => '4996', 'name' => 'Other', 'type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $posted = $this->app->make(CreateAndPostOtherBankTransaction::class)->execute($this->admin(self::A), $this->bank(self::A), new LedgerAccountId(new Uuid($contra)), new Money('15', new Currency('EUR')), new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReference('CORRUPT'), new TransactionDescription('Corrupt'), $this->user());
        $id = $posted->bankTransactionId;
        self::assertNotNull($id);
        $beforeJournals = DB::table('journal_entries')->count();
        DB::table('payments')->insert(['id' => 'b27a0000-0000-4000-8000-000000000011', 'administration_id' => self::A, 'bank_transaction_id' => $id->toString(), 'relation_id' => $this->relation(self::A)->toString(), 'type' => 'customer_receipt', 'amount' => '15', 'currency' => 'EUR']);

        try {
            $this->repo()->find($this->admin(self::A), $id);
            self::fail('Repository selected one of two persisted intents.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        self::assertSame(ReverseBankTransactionStatus::FinancialStateInvalid, $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReversalReason('Corrupt both'), $this->user())->status);
        self::assertSame($beforeJournals, DB::table('journal_entries')->count());

        DB::table('payments')->where('bank_transaction_id', $id->toString())->delete();
        DB::table('other_bank_transaction_intents')->where('bank_transaction_id', $id->toString())->delete();
        try {
            $this->repo()->find($this->admin(self::A), $id);
            self::fail('Repository reconstituted a transaction without intent.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        self::assertSame(ReverseBankTransactionStatus::FinancialStateInvalid, $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), new BankTransactionReversalReason('Corrupt none'), $this->user())->status);
        self::assertSame($beforeJournals, DB::table('journal_entries')->count());
    }

    public function test_supplier_payment_uses_historical_payable_control_account(): void
    {
        [, $id] = $this->create()->execute($this->admin(self::B), $this->bank(self::B), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('-100', new Currency('EUR')), new BankTransactionReference('POST-PAY'), new TransactionDescription('Supplier'), $this->relation(self::B), $this->user(), [$this->allocation(3, '100')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::B), $id, $this->user()));
        $this->configure(self::B);
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::B), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        $entry = DB::table('bank_transaction_postings')->where('bank_transaction_id', $id->toString())->value('journal_entry_id');
        $line = DB::table('journal_entry_lines')->where('journal_entry_id', $entry)->where('ledger_account_id', $this->ledger(self::B))->first();
        self::assertSame(0, bccomp('100', (string) $line?->debit_amount, 4));
        self::assertSame(0, DB::table('open_item_matches')->count());
    }

    public function test_each_persistence_failure_rolls_back_all_financial_facts(): void
    {
        $this->configure(self::A);
        $realEntries = $this->app->make(JournalEntryStore::class);
        $realSettlements = $this->app->make(OpenItemSettlementStore::class);
        $realLinkages = $this->app->make(BankTransactionPostingRepository::class);
        $realTransactions = $this->repo();

        foreach (['journal', 'settlement', 'linkage', 'status'] as $index => $boundary) {
            [, $id] = $this->createFinalized(self::A, '100', 30 + $index, 1, 'ROLLBACK-'.strtoupper($boundary));
            if ($boundary === 'journal') {
                $store = $this->createMock(JournalEntryStore::class);
                $store->method('append')->willThrowException(new \RuntimeException('Forced journal failure.'));
                $this->app->instance(JournalEntryStore::class, $store);
            } elseif ($boundary === 'settlement') {
                $store = $this->createMock(OpenItemSettlementStore::class);
                $store->method('appendSettlement')->willThrowException(new \RuntimeException('Forced settlement failure.'));
                $this->app->instance(OpenItemSettlementStore::class, $store);
            } elseif ($boundary === 'linkage') {
                $store = $this->createMock(BankTransactionPostingRepository::class);
                $store->method('exists')->willReturn(false);
                $store->method('append')->willThrowException(new \RuntimeException('Forced linkage failure.'));
                $this->app->instance(BankTransactionPostingRepository::class, $store);
            } else {
                $this->app->instance(BankTransactionRepository::class, new class($realTransactions) implements BankTransactionRepository
                {
                    public function __construct(private BankTransactionRepository $inner) {}

                    public function save(BankTransaction $transaction): void
                    {
                        throw new \RuntimeException('Forced status failure.');
                    }

                    public function find(AdministrationId $admin, BankTransactionId $id, bool $forUpdate = false): ?BankTransaction
                    {
                        return $this->inner->find($admin, $id, $forUpdate);
                    }

                    public function list(AdministrationId $admin): array
                    {
                        return $this->inner->list($admin);
                    }
                });
            }

            self::assertSame(PostBankTransactionStatus::PostingFailure, $this->app->make(PostBankTransaction::class)->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()), $boundary);
            self::assertSame('finalized', DB::table('bank_transactions')->where('id', $id->toString())->value('status'), $boundary);
            self::assertSame(0, DB::table('journal_entries')->where('reference', 'ROLLBACK-'.strtoupper($boundary))->count(), $boundary);
            self::assertSame(0, DB::table('bank_transaction_postings')->where('bank_transaction_id', $id->toString())->count(), $boundary);
            self::assertSame(0, DB::table('open_item_settlements')->where('payment_allocation_id', $this->allocationFor(30 + $index, 1, '100')->id->toString())->count(), $boundary);

            $this->app->instance(JournalEntryStore::class, $realEntries);
            $this->app->instance(OpenItemSettlementStore::class, $realSettlements);
            $this->app->instance(BankTransactionPostingRepository::class, $realLinkages);
            $this->app->instance(BankTransactionRepository::class, $realTransactions);
        }
    }

    public function test_customer_receipt_reversal_mirrors_historical_multi_allocation_posting_and_restores_balances(): void
    {
        $this->configure(self::A);
        [, $id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('150', new Currency('EUR')), new BankTransactionReference('REV-MULTI'), new TransactionDescription('Multi receipt'), $this->relation(self::A), $this->user(), [$this->allocation(1, '100'), $this->allocation(2, '50')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        $originalEntry = DB::table('bank_transaction_postings')->where('bank_transaction_id', $id->toString())->value('journal_entry_id');
        $originalLines = DB::table('journal_entry_lines')->where('journal_entry_id', $originalEntry)->orderBy('id')->get();
        $taxBefore = DB::table('tax_postings')->count();
        DB::table('journals')->where('id', str_replace('b270', 'b278', self::A))->update(['status' => 'inactive']);
        DB::table('ledger_accounts')->whereIn('id', [str_replace('b270', 'b279', self::A), $this->ledger(self::A)])->update(['status' => 'inactive', 'name' => 'Historical renamed']);

        $result = $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Incorrect bank receipt'), $this->user());
        self::assertSame(ReverseBankTransactionStatus::Success, $result->status);
        self::assertSame(2, $result->success?->reversedSettlementCount);
        self::assertSame($originalEntry, $result->success?->originalJournalEntryId->toString());
        self::assertSame('posted', DB::table('bank_transactions')->where('id', $id->toString())->value('status'));
        self::assertSame(1, DB::table('bank_transaction_reversals')->where('original_bank_transaction_id', $id->toString())->count());
        self::assertSame(2, DB::table('bank_transaction_settlement_reversal_links')->count());
        self::assertSame(2, DB::table('open_item_settlements')->where('type', 'reversal')->count());
        self::assertSame(2, DB::table('open_item_settlements')->where('type', 'applied')->count());
        self::assertSame($taxBefore, DB::table('tax_postings')->count());
        $reversalEntry = $result->success?->reversalJournalEntryId->toString();
        self::assertSame(str_replace('b270', 'b278', self::A), DB::table('journal_entries')->where('id', $reversalEntry)->value('journal_id'));
        $contraLines = DB::table('journal_entry_lines')->where('journal_entry_id', $reversalEntry)->get()->keyBy('description');
        self::assertCount(3, $contraLines);
        foreach ($originalLines as $line) {
            $contra = $contraLines->get('Reversal '.$line->id);
            self::assertNotNull($contra);
            self::assertSame($line->ledger_account_id, $contra->ledger_account_id);
            self::assertSame($line->debit_amount, $contra->credit_amount);
            self::assertSame($line->credit_amount, $contra->debit_amount);
            self::assertSame($line->currency, $contra->currency);
        }
        self::assertSame(0, bccomp('100', (string) $this->openItems()->findForAdministration($this->admin(self::A), new OpenItemId(new Uuid($this->item(1))))?->openAmount()->amount(), 8));
        self::assertSame(0, bccomp('50', (string) $this->openItems()->findForAdministration($this->admin(self::A), new OpenItemId(new Uuid($this->item(2))))?->openAmount()->amount(), 8));

        $again = $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-29')), new BankTransactionReversalReason('Second attempt'), $this->user());
        self::assertSame(ReverseBankTransactionStatus::AlreadyReversed, $again->status);
        self::assertSame(1, DB::table('bank_transaction_reversals')->count());
        self::assertSame(2, DB::table('open_item_settlements')->where('type', 'reversal')->count());
    }

    public function test_bank_reversal_uses_reversal_posting_date_and_period_denials_are_side_effect_free(): void
    {
        $this->configure(self::A);
        [, $id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('100', new Currency('EUR')), new BankTransactionReference('PERIOD-REV'), new TransactionDescription('Period reversal'), $this->relation(self::A), $this->user(), [$this->allocation(1, '100')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        $before = [DB::table('journal_entries')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_reversals')->count()];
        DB::table('accounting_periods')->where('administration_id', self::A)->update(['status' => 'closed']);
        self::assertSame(ReverseBankTransactionStatus::PeriodClosed, $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Period closed'), $this->user())->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_reversals')->count()]);
        DB::table('accounting_periods')->where('administration_id', self::A)->delete();
        self::assertSame(ReverseBankTransactionStatus::NoAccountingPeriod, $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('No period'), $this->user())->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_reversals')->count()]);
    }

    public function test_supplier_payment_and_other_payments_and_matches_remain_independent(): void
    {
        DB::table('open_items')->where('id', $this->item(3))->update(['original_amount' => '121']);
        $this->configure(self::B);
        [, $paymentA] = $this->create()->execute($this->admin(self::B), $this->bank(self::B), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('-40', new Currency('EUR')), new BankTransactionReference('SUP-A'), new TransactionDescription('Supplier A'), $this->relation(self::B), $this->user(), [$this->allocationFor(71, 3, '40')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::B), $paymentA, $this->user()));
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::B), $paymentA, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        DB::table('open_items')->insert(['id' => $this->item(4), 'administration_id' => self::B, 'relation_id' => $this->relation(self::B)->toString(), 'journal_entry_id' => $this->entry(self::B), 'control_ledger_account_id' => $this->ledger(self::B), 'open_item_type' => 'payable', 'side' => 'debit', 'original_amount' => '121', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('open_item_matches')->insert(['id' => $this->item(5), 'administration_id' => self::B, 'debit_open_item_id' => $this->item(4), 'credit_open_item_id' => $this->item(3), 'amount' => '81', 'currency' => 'EUR', 'occurred_on' => '2026-08-27', 'source_journal_entry_id' => $this->entry(self::B), 'created_at' => now(), 'updated_at' => now()]);
        self::assertTrue($this->openItems()->findForAdministration($this->admin(self::B), new OpenItemId(new Uuid($this->item(3))))?->openAmount()->isZero());

        $result = $this->reverse()->execute($this->admin(self::B), $paymentA, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Supplier payment correction'), $this->user());
        self::assertSame(ReverseBankTransactionStatus::Success, $result->status);
        self::assertSame(0, bccomp('40', (string) $this->openItems()->findForAdministration($this->admin(self::B), new OpenItemId(new Uuid($this->item(3))))?->openAmount()->amount(), 8));
        self::assertSame(0, bccomp('40', (string) $this->openItems()->findForAdministration($this->admin(self::B), new OpenItemId(new Uuid($this->item(4))))?->openAmount()->amount(), 8));
        self::assertSame(1, DB::table('open_item_matches')->count());

        DB::table('open_items')->where('id', $this->item(1))->update(['original_amount' => '121']);
        $this->configure(self::A);
        [, $receiptA] = $this->createFinalized(self::A, '40', 72, 1, 'RECEIPT-A');
        [, $receiptB] = $this->createFinalized(self::A, '81', 73, 1, 'RECEIPT-B');
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $receiptA, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $receiptB, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        self::assertSame(ReverseBankTransactionStatus::Success, $this->reverse()->execute($this->admin(self::A), $receiptA, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Reverse payment A'), $this->user())->status);
        self::assertSame(0, bccomp('40', (string) $this->openItems()->findForAdministration($this->admin(self::A), new OpenItemId(new Uuid($this->item(1))))?->openAmount()->amount(), 8));
        self::assertSame(1, DB::table('open_item_settlements')->where('payment_allocation_id', $this->allocationFor(73, 1, '81')->id->toString())->count());
    }

    public function test_reversal_failures_at_each_write_boundary_roll_back_everything(): void
    {
        $this->configure(self::A);
        foreach (['journal', 'settlement', 'reversal', 'link'] as $index => $boundary) {
            DB::table('open_items')->where('id', $this->item(1))->update(['original_amount' => (string) (400 + $index * 100)]);
            [, $id] = $this->createFinalized(self::A, '100', 80 + $index, 1, 'REV-FAIL-'.$boundary);
            self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
            $before = [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_reversals')->count(), DB::table('bank_transaction_settlement_reversal_links')->count()];
            if ($boundary === 'journal') {
                $mock = $this->createMock(JournalEntryStore::class);
                $mock->method('append')->willThrowException(new \RuntimeException('journal'));
                $this->app->instance(JournalEntryStore::class, $mock);
            } elseif ($boundary === 'settlement') {
                $mock = $this->createMock(OpenItemSettlementStore::class);
                $mock->method('appendSettlement')->willThrowException(new \RuntimeException('settlement'));
                $this->app->instance(OpenItemSettlementStore::class, $mock);
            } elseif ($boundary === 'reversal') {
                $mock = $this->createMock(BankTransactionReversalRepository::class);
                $mock->method('appendReversal')->willReturn(false);
                $this->app->instance(BankTransactionReversalRepository::class, $mock);
            } else {
                $mock = $this->createMock(BankTransactionSettlementReversalLinkRepository::class);
                $mock->method('appendLink')->willReturn(false);
                $this->app->instance(BankTransactionSettlementReversalLinkRepository::class, $mock);
            }
            self::assertSame(ReverseBankTransactionStatus::PostingFailure, $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Forced rollback'), $this->user())->status, $boundary);
            self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('open_item_settlements')->count(), DB::table('bank_transaction_reversals')->count(), DB::table('bank_transaction_settlement_reversal_links')->count()], $boundary);
            $this->app->forgetInstance(JournalEntryStore::class);
            $this->app->forgetInstance(OpenItemSettlementStore::class);
            $this->app->forgetInstance(BankTransactionReversalRepository::class);
            $this->app->forgetInstance(BankTransactionSettlementReversalLinkRepository::class);
        }
    }

    public function test_fully_paid_purchase_credit_and_sales_credit_matches_remain_independent(): void
    {
        $this->configure(self::B);
        $this->openItem(self::B, 6, 'payable', 'credit', '121');
        [, $supplierPayment] = $this->create()->execute($this->admin(self::B), $this->bank(self::B), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('-121', new Currency('EUR')), new BankTransactionReference('FULLY-PAID'), new TransactionDescription('Fully paid supplier invoice'), $this->relation(self::B), $this->user(), [$this->allocationFor(95, 6, '121')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::B), $supplierPayment, $this->user()));
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::B), $supplierPayment, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        $this->openItem(self::B, 7, 'payable', 'debit', '121');
        self::assertSame(ReverseBankTransactionStatus::Success, $this->reverse()->execute($this->admin(self::B), $supplierPayment, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Reverse fully paid supplier payment'), $this->user())->status);
        self::assertSame(0, bccomp('121', (string) $this->openItems()->findForAdministration($this->admin(self::B), new OpenItemId(new Uuid($this->item(6))))?->openAmount()->amount(), 8));
        self::assertSame(0, bccomp('121', (string) $this->openItems()->findForAdministration($this->admin(self::B), new OpenItemId(new Uuid($this->item(7))))?->openAmount()->amount(), 8));

        $this->configure(self::A);
        $this->openItem(self::A, 8, 'receivable', 'debit', '121');
        [, $receipt] = $this->createFinalized(self::A, '40', 96, 8, 'SALES-CREDIT');
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $receipt, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        $this->openItem(self::A, 9, 'receivable', 'credit', '121');
        self::assertSame('Success', $this->app->make(MatchOpenItems::class)->execute($this->admin(self::A), new OpenItemId(new Uuid($this->item(8))), new OpenItemId(new Uuid($this->item(9))), new Money('81', new Currency('EUR')), new PostingDate(new DateTimeImmutable('2026-08-27')), new JournalEntryId(new Uuid($this->entry(self::A))))->status->name);
        self::assertSame(ReverseBankTransactionStatus::Success, $this->reverse()->execute($this->admin(self::A), $receipt, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Reverse receipt after sales credit'), $this->user())->status);
        self::assertSame(0, bccomp('40', (string) $this->openItems()->findForAdministration($this->admin(self::A), new OpenItemId(new Uuid($this->item(8))))?->openAmount()->amount(), 8));
        self::assertSame(0, bccomp('40', (string) $this->openItems()->findForAdministration($this->admin(self::A), new OpenItemId(new Uuid($this->item(9))))?->openAmount()->amount(), 8));
        self::assertSame(1, DB::table('open_item_matches')->where('administration_id', self::A)->count());
    }

    public function test_failure_on_second_settlement_reversal_rolls_back_first_and_contra_journal(): void
    {
        $this->configure(self::A);
        [, $id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('150', new Currency('EUR')), new BankTransactionReference('SECOND-FAIL'), new TransactionDescription('Second settlement failure'), $this->relation(self::A), $this->user(), [$this->allocationFor(97, 1, '100'), $this->allocationFor(98, 2, '50')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::A), $id, $this->user()));
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        $real = $this->app->make(OpenItemSettlementStore::class);
        $this->app->instance(OpenItemSettlementStore::class, new class($real) implements OpenItemSettlementStore
        {
            private int $calls = 0;

            public function __construct(private OpenItemSettlementStore $inner) {}

            public function appendSettlement(OpenItem $openItem, OpenItemSettlement $settlement, ?PaymentAllocationId $paymentAllocationId = null): void
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new \RuntimeException('Forced second settlement failure.');
                }
                $this->inner->appendSettlement($openItem, $settlement, $paymentAllocationId);
            }
        });
        $before = [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('open_item_settlements')->count()];
        self::assertSame(ReverseBankTransactionStatus::PostingFailure, $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Rollback both settlements'), $this->user())->status);
        self::assertSame($before, [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('open_item_settlements')->count()]);
        self::assertSame(0, DB::table('bank_transaction_reversals')->count());
        self::assertSame(0, DB::table('bank_transaction_settlement_reversal_links')->count());
    }

    public function test_real_mysql_double_finalize_has_one_success_and_one_already_finalized(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        [, $id] = $this->create()->execute($this->admin(self::A), $this->bank(self::A), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('100', new Currency('EUR')), new BankTransactionReference('RACE'), new TransactionDescription('Race'), $this->relation(self::A), $this->user(), [$this->allocation(1, '100')]);
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'bank-finalize-'), tempnam(sys_get_temp_dir(), 'bank-finalize-')];
        $children = [];
        foreach ($files as $file) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $this->finalize()->execute($this->admin(self::A), $id, $this->user())->name);
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
        }
        $results = array_map(static fn ($file) => trim((string) file_get_contents($file)), $files);
        sort($results);
        self::assertSame(['AlreadyFinalized', 'Success'], $results);
        self::assertSame(1, DB::table('payments')->where('bank_transaction_id', $id->toString())->count());
        self::assertSame(1, DB::table('payment_allocations')->count());
        foreach ($files as $file) {
            unlink($file);
        }
        $this->cleanupCommittedFixtures();
    }

    public function test_real_mysql_double_post_creates_one_financial_truth(): void
    {
        $this->configure(self::A);
        [, $id] = $this->createFinalized(self::A, '100', 11, 1, 'DOUBLE');
        $results = $this->runConcurrentPosts([$id, $id]);

        sort($results);
        self::assertSame(['AlreadyPosted', 'Success'], $results);
        self::assertSame(1, DB::table('bank_transaction_postings')->where('bank_transaction_id', $id->toString())->count());
        self::assertSame(1, DB::table('open_item_settlements')->whereNotNull('payment_allocation_id')->count());
        self::assertSame(1, DB::table('journal_entries')->where('reference', 'DOUBLE')->count());
        self::assertSame('posted', DB::table('bank_transactions')->where('id', $id->toString())->value('status'));
        $this->cleanupCommittedFixtures();
    }

    public function test_real_mysql_double_reversal_creates_one_complete_financial_truth(): void
    {
        $this->configure(self::A);
        [, $id] = $this->createFinalized(self::A, '100', 91, 1, 'DOUBLE-REV');
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'bank-reverse-'), tempnam(sys_get_temp_dir(), 'bank-reverse-')];
        $children = [];
        foreach ($files as $file) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->reverse()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Concurrent correction'), $this->user());
                    file_put_contents($file, $result->status->name);
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
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        sort($results);
        self::assertSame(['AlreadyReversed', 'Success'], $results);
        self::assertSame(1, DB::table('bank_transaction_reversals')->count());
        self::assertSame(1, DB::table('bank_transaction_settlement_reversal_links')->count());
        self::assertSame(1, DB::table('open_item_settlements')->where('type', 'reversal')->count());
        self::assertSame(1, DB::table('journal_entries')->where('reference', 'REV-'.$id->toString())->count());
        foreach ($files as $file) {
            unlink($file);
        }
        $this->cleanupCommittedFixtures();
    }

    public function test_real_mysql_reversal_and_new_payment_are_serializable(): void
    {
        $this->configure(self::A);
        for ($iteration = 0; $iteration < 3; $iteration++) {
            $item = 10 + $iteration;
            $this->openItem(self::A, $item, 'receivable', 'debit', '100');
            [, $paymentA] = $this->createFinalized(self::A, '100', 100 + ($iteration * 2), $item, 'RACE-REV-A-'.$iteration);
            [, $paymentB] = $this->createFinalized(self::A, '100', 101 + ($iteration * 2), $item, 'RACE-POST-B-'.$iteration);
            self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::A), $paymentA, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
            $results = $this->runConcurrentBankOperations(
                fn (): string => 'reverse:'.$this->reverse()->execute($this->admin(self::A), $paymentA, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Concurrent payment correction'), $this->user())->status->name,
                fn (): string => 'payment:'.$this->postBankTransaction()->execute($this->admin(self::A), $paymentB, new PostingDate(new DateTimeImmutable('2026-08-28')), $this->user())->name,
            );
            self::assertContains('reverse:Success', $results);
            self::assertNotContains('payment:PostingFailure', $results);
            self::assertTrue(in_array('payment:Success', $results, true) || in_array('payment:AllocationExceedsOpenBalance', $results, true), implode(', ', $results));

            $reversalId = DB::table('bank_transaction_reversals')->where('original_bank_transaction_id', $paymentA->toString())->value('id');
            self::assertNotNull($reversalId);
            self::assertSame(1, DB::table('bank_transaction_reversals')->where('original_bank_transaction_id', $paymentA->toString())->count());
            self::assertSame(1, DB::table('bank_transaction_settlement_reversal_links')->where('bank_transaction_reversal_id', $reversalId)->count());
            self::assertLessThanOrEqual(1, DB::table('bank_transaction_postings')->where('bank_transaction_id', $paymentB->toString())->count());
            self::assertSame(0, DB::table('open_item_settlements')->where('open_item_id', $this->item($item))->select('payment_allocation_id', 'type')->groupBy('payment_allocation_id', 'type')->havingRaw('COUNT(*) > 1')->count());
            self::assertSame(0, DB::table('bank_transaction_settlement_reversal_links as l')->leftJoin('bank_transaction_reversals as r', 'r.id', '=', 'l.bank_transaction_reversal_id')->where('l.bank_transaction_reversal_id', $reversalId)->whereNull('r.id')->count());

            $open = $this->openItems()->findForAdministration($this->admin(self::A), new OpenItemId(new Uuid($this->item($item))))?->openAmount();
            self::assertNotNull($open);
            self::assertFalse($open->isNegative());
            self::assertFalse($open->subtract(new Money('100', new Currency('EUR')))->isPositive());
        }
        $this->cleanupCommittedFixtures();
    }

    public function test_real_mysql_supplier_reversal_and_purchase_credit_style_match_are_serializable(): void
    {
        DB::table('open_items')->where('id', $this->item(3))->update(['original_amount' => '121']);
        $this->configure(self::B);
        [, $payment] = $this->create()->execute($this->admin(self::B), $this->bank(self::B), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money('-40', new Currency('EUR')), new BankTransactionReference('RACE-CREDIT'), new TransactionDescription('Payment before credit'), $this->relation(self::B), $this->user(), [$this->allocationFor(94, 3, '40')]);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin(self::B), $payment, $this->user()));
        self::assertSame(PostBankTransactionStatus::Success, $this->postBankTransaction()->execute($this->admin(self::B), $payment, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user()));
        DB::table('open_items')->insert(['id' => $this->item(4), 'administration_id' => self::B, 'relation_id' => $this->relation(self::B)->toString(), 'journal_entry_id' => $this->entry(self::B), 'control_ledger_account_id' => $this->ledger(self::B), 'open_item_type' => 'payable', 'side' => 'debit', 'original_amount' => '121', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => now(), 'updated_at' => now()]);
        $results = $this->runConcurrentBankOperations(
            fn (): string => 'reverse:'.$this->reverse()->execute($this->admin(self::B), $payment, new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Concurrent supplier correction'), $this->user())->status->name,
            fn (): string => 'match:'.$this->app->make(MatchOpenItems::class)->executeAvailable($this->admin(self::B), new OpenItemId(new Uuid($this->item(4))), new OpenItemId(new Uuid($this->item(3))), new PostingDate(new DateTimeImmutable('2026-08-28')), new JournalEntryId(new Uuid($this->entry(self::B))))->status->name,
        );
        self::assertContains('reverse:Success', $results);
        self::assertContains('match:Success', $results);
        self::assertSame(1, DB::table('open_item_matches')->count());
        self::assertSame(1, DB::table('open_item_settlements')->where('type', 'reversal')->count());
        $source = $this->openItems()->findForAdministration($this->admin(self::B), new OpenItemId(new Uuid($this->item(3))));
        $credit = $this->openItems()->findForAdministration($this->admin(self::B), new OpenItemId(new Uuid($this->item(4))));
        self::assertNotNull($source);
        self::assertNotNull($credit);
        self::assertFalse($source->openAmount()->isNegative());
        self::assertFalse($credit->openAmount()->isNegative());
        self::assertTrue(($source->openAmount()->isZero() && $credit->openAmount()->isZero()) || ($source->openAmount()->equals(new Money('40', new Currency('EUR'))) && $credit->openAmount()->equals(new Money('40', new Currency('EUR')))));
        $this->cleanupCommittedFixtures();
    }

    public function test_real_mysql_competing_over_allocation_allows_only_one_post(): void
    {
        DB::table('open_items')->where('id', $this->item(1))->update(['original_amount' => '1000']);
        $this->configure(self::A);
        [, $a] = $this->createFinalized(self::A, '600', 11, 1, 'OVER-A');
        [, $b] = $this->createFinalized(self::A, '600', 12, 1, 'OVER-B');
        $results = $this->runConcurrentPosts([$a, $b]);

        sort($results);
        self::assertSame(['AllocationExceedsOpenBalance', 'Success'], $results);
        self::assertSame(1, DB::table('bank_transaction_postings')->count());
        self::assertSame(1, DB::table('open_item_settlements')->whereNotNull('payment_allocation_id')->count());
        self::assertSame(0, bccomp('600', (string) DB::table('open_item_settlements')->sum('amount'), 4));
        self::assertSame(1, DB::table('bank_transactions')->where('status', 'finalized')->count());
        self::assertSame(1, DB::table('bank_transactions')->where('status', 'posted')->count());
        $this->cleanupCommittedFixtures();
    }

    public function test_real_mysql_compatible_split_serializes_and_fully_settles(): void
    {
        DB::table('open_items')->where('id', $this->item(1))->update(['original_amount' => '1000']);
        $this->configure(self::A);
        [, $a] = $this->createFinalized(self::A, '600', 11, 1, 'SPLIT-A');
        [, $b] = $this->createFinalized(self::A, '400', 12, 1, 'SPLIT-B');
        $results = $this->runConcurrentPosts([$a, $b]);

        self::assertSame(['Success', 'Success'], $results);
        self::assertSame(2, DB::table('bank_transaction_postings')->count());
        self::assertSame(2, DB::table('open_item_settlements')->whereNotNull('payment_allocation_id')->count());
        self::assertSame(0, bccomp('1000', (string) DB::table('open_item_settlements')->sum('amount'), 4));
        self::assertSame(2, DB::table('bank_transactions')->where('status', 'posted')->count());
        $this->cleanupCommittedFixtures();
    }

    private function create(): CreateManualBankTransaction
    {
        return $this->app->make(CreateManualBankTransaction::class);
    }

    private function finalize(): FinalizeBankTransaction
    {
        return $this->app->make(FinalizeBankTransaction::class);
    }

    private function postBankTransaction(): PostBankTransaction
    {
        return $this->app->make(PostBankTransaction::class);
    }

    private function reverse(): ReverseBankTransaction
    {
        return $this->app->make(ReverseBankTransaction::class);
    }

    private function openItems(): OpenItemReadRepository
    {
        return $this->app->make(OpenItemReadRepository::class);
    }

    private function configure(string $admin): void
    {
        $bankJournal = str_replace('b270', 'b278', $admin);
        $bankLedger = str_replace('b270', 'b279', $admin);
        $now = now();
        DB::table('journals')->insert(['id' => $bankJournal, 'administration_id' => $admin, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'bank', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('ledger_accounts')->insert(['id' => $bankLedger, 'administration_id' => $admin, 'code' => '1100', 'name' => 'Bank', 'type' => 'asset', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('banking_posting_configurations')->insert(['administration_id' => $admin, 'administration_bank_account_id' => $this->bank($admin)->toString(), 'bank_journal_id' => $bankJournal, 'bank_ledger_account_id' => $bankLedger, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function repo(): BankTransactionRepository
    {
        return $this->app->make(BankTransactionRepository::class);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function bank(string $id): AdministrationBankAccountId
    {
        return new AdministrationBankAccountId(new Uuid(str_replace('b270', 'b271', $id)));
    }

    private function relation(string $id): RelationId
    {
        return new RelationId(new Uuid(str_replace('b270', 'b272', $id)));
    }

    private function user(): UserId
    {
        return new UserId(new Uuid(self::USER));
    }

    private function ledger(string $id): string
    {
        return str_replace('b270', 'b273', $id);
    }

    private function entry(string $id): string
    {
        return str_replace('b270', 'b274', $id);
    }

    private function journal(string $id): string
    {
        return str_replace('b270', 'b277', $id);
    }

    private function item(int $n): string
    {
        return sprintf('b2750000-0000-4000-8000-%012d', $n);
    }

    private function allocation(int $n, string $amount): BankTransactionAllocationInput
    {
        return new BankTransactionAllocationInput(new PaymentAllocationId(new Uuid(sprintf('b2760000-0000-4000-8000-%012d', $n))), new OpenItemId(new Uuid($this->item($n))), new Money($amount, new Currency('EUR')));
    }

    private function allocationFor(int $allocation, int $item, string $amount): BankTransactionAllocationInput
    {
        return new BankTransactionAllocationInput(new PaymentAllocationId(new Uuid(sprintf('b2760000-0000-4000-8000-%012d', $allocation))), new OpenItemId(new Uuid($this->item($item))), new Money($amount, new Currency('EUR')));
    }

    private function createFinalized(string $admin, string $amount, int $allocation, int $item, string $reference): array
    {
        [$result, $id] = $this->create()->execute($this->admin($admin), $this->bank($admin), new TransactionDate(new DateTimeImmutable('2026-08-26')), new Money($amount, new Currency('EUR')), new BankTransactionReference($reference), new TransactionDescription($reference), $this->relation($admin), $this->user(), [$this->allocationFor($allocation, $item, $amount)]);
        self::assertSame(BankTransactionResult::Success, $result);
        self::assertNotNull($id);
        self::assertSame(BankTransactionResult::Success, $this->finalize()->execute($this->admin($admin), $id, $this->user()));

        return [$result, $id];
    }

    /** @param list<BankTransactionId> $ids @return list<string> */
    private function runConcurrentPosts(array $ids): array
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        DB::commit();
        $files = array_map(static fn (): string => (string) tempnam(sys_get_temp_dir(), 'bank-post-'), $ids);
        $children = [];
        foreach ($ids as $index => $id) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->postBankTransaction()->execute($this->admin(self::A), $id, new PostingDate(new DateTimeImmutable('2026-08-27')), $this->user());
                    file_put_contents($files[$index], $result->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($files[$index], 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $results;
    }

    /** @return list<string> */
    private function runConcurrentBankOperations(callable $first, callable $second): array
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        DB::commit();
        $files = [(string) tempnam(sys_get_temp_dir(), 'bank-op-'), (string) tempnam(sys_get_temp_dir(), 'bank-op-')];
        $children = [];
        foreach ([[$files[0], $first], [$files[1], $second]] as [$file, $operation]) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $operation());
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
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $results;
    }

    private function cleanupCommittedFixtures(): void
    {
        DB::table('bank_transaction_settlement_reversal_links')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('bank_transaction_reversals')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('open_item_settlements')->whereIn('administration_id', [self::A, self::B])->where('type', 'reversal')->delete();
        DB::table('open_item_settlements')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('open_item_matches')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('bank_transaction_postings')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('journal_entry_lines')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('payment_allocations')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('payments')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('other_bank_transaction_intents')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('bank_transactions')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('banking_posting_configurations')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('open_items')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('administration_bank_accounts')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('journal_entries')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('journals')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('ledger_accounts')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('relations')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('accounting_periods')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('book_years')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('administrations')->whereIn('id', [self::A, self::B])->delete();
        DB::table('domain_users')->where('id', self::USER)->delete();
        DB::beginTransaction();
    }

    private function openItem(string $admin, int $n, string $type, string $side, string $amount): void
    {
        DB::table('open_items')->insert(['id' => $this->item($n), 'administration_id' => $admin, 'relation_id' => $this->relation($admin)->toString(), 'journal_entry_id' => $this->entry($admin), 'control_ledger_account_id' => $this->ledger($admin), 'open_item_type' => $type, 'side' => $side, 'original_amount' => $amount, 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function financialCounts(): array
    {
        return [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('open_item_settlements')->count(), DB::table('open_item_matches')->count()];
    }
}
