<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Banking\BankTransactionAllocationInput;
use App\Application\Banking\BankTransactionRepository;
use App\Application\Banking\BankTransactionResult;
use App\Application\Banking\CancelBankTransaction;
use App\Application\Banking\CreateManualBankTransaction;
use App\Application\Banking\FinalizeBankTransaction;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
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
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn ($file) => trim((string) file_get_contents($file)), $files);
        sort($results);
        self::assertSame(['AlreadyFinalized', 'Success'], $results);
        self::assertSame(1, DB::table('payments')->where('bank_transaction_id', $id->toString())->count());
        self::assertSame(1, DB::table('payment_allocations')->count());
        foreach ($files as $file) {
            unlink($file);
        }
        DB::table('payment_allocations')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('payments')->whereIn('administration_id', [self::A, self::B])->delete();
        DB::table('bank_transactions')->whereIn('administration_id', [self::A, self::B])->delete();
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

    private function create(): CreateManualBankTransaction
    {
        return $this->app->make(CreateManualBankTransaction::class);
    }

    private function finalize(): FinalizeBankTransaction
    {
        return $this->app->make(FinalizeBankTransaction::class);
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

    private function openItem(string $admin, int $n, string $type, string $side, string $amount): void
    {
        DB::table('open_items')->insert(['id' => $this->item($n), 'administration_id' => $admin, 'relation_id' => $this->relation($admin)->toString(), 'journal_entry_id' => $this->entry($admin), 'control_ledger_account_id' => $this->ledger($admin), 'open_item_type' => $type, 'side' => $side, 'original_amount' => $amount, 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function financialCounts(): array
    {
        return [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('open_item_settlements')->count(), DB::table('open_item_matches')->count()];
    }
}
