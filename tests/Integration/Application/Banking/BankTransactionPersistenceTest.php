<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Banking\BankTransactionAllocationInput;
use App\Application\Banking\BankTransactionPostingRepository;
use App\Application\Banking\BankTransactionRepository;
use App\Application\Banking\BankTransactionResult;
use App\Application\Banking\CancelBankTransaction;
use App\Application\Banking\CreateManualBankTransaction;
use App\Application\Banking\FinalizeBankTransaction;
use App\Application\Banking\GetBankTransactionPostingDetail;
use App\Application\Banking\PostBankTransaction;
use App\Application\Banking\PostBankTransactionStatus;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
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

    private function cleanupCommittedFixtures(): void
    {
        DB::table('open_item_settlements')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('bank_transaction_postings')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('journal_entry_lines')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('payment_allocations')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('payments')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('bank_transactions')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('banking_posting_configurations')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('open_items')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('administration_bank_accounts')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('journal_entries')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('journals')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('ledger_accounts')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('relations')->whereIn('administration_id', [self::A, self::B])->delete();
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
