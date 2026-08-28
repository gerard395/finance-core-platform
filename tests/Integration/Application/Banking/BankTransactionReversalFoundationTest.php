<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Banking\AssessBankTransactionReversalEligibility;
use App\Application\Banking\BankTransactionReversalEligibilityStatus;
use App\Application\Banking\BankTransactionReversalRepository;
use App\Application\Banking\BankTransactionReversalSourceReader;
use App\Application\Banking\BankTransactionSettlementReversalLinkRepository;
use App\Application\Banking\GetBankTransactionReversalReadiness;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransactionReversal;
use App\Domain\Banking\Entities\BankTransactionSettlementReversalLink;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Banking\ValueObjects\BankTransactionSettlementReversalLinkId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class BankTransactionReversalFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'b3100000-0000-4000-8000-000000000001';

    private const B = 'b3100000-0000-4000-8000-000000000002';

    private const USER = 'b3100000-0000-4000-8000-000000000003';

    private const TX = 'b3100000-0000-4000-8000-000000000010';

    private const PAYMENT = 'b3100000-0000-4000-8000-000000000011';

    private const ALLOCATION = 'b3100000-0000-4000-8000-000000000012';

    private const POSTING = 'b3100000-0000-4000-8000-000000000013';

    private const ORIGINAL_ENTRY = 'b3100000-0000-4000-8000-000000000014';

    private const REVERSAL_ENTRY = 'b3100000-0000-4000-8000-000000000015';

    private const ITEM = 'b3100000-0000-4000-8000-000000000016';

    private const APPLIED = 'b3100000-0000-4000-8000-000000000017';

    private const SETTLEMENT_REVERSAL = 'b3100000-0000-4000-8000-000000000018';

    private const REVERSAL = 'b3100000-0000-4000-8000-000000000019';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures();
    }

    public function test_value_contracts_and_eligible_historical_source_are_exact_and_side_effect_free(): void
    {
        self::assertSame('trimmed', BankTransactionReversalReason::fromUserInput('  trimmed  ')->value());
        foreach (['', ' ', str_repeat('x', 501)] as $invalid) {
            try {
                new BankTransactionReversalReason($invalid);
                self::fail('Invalid reason accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        $before = $this->financialCounts();
        self::assertSame(BankTransactionReversalEligibilityStatus::Eligible, $this->eligibility()->execute($this->admin(self::A), $this->tx()));
        $source = $this->app->make(BankTransactionReversalSourceReader::class)->read($this->admin(self::A), $this->tx());
        self::assertSame(self::PAYMENT, $source?->transaction->payment()->id()->toString());
        self::assertSame(self::POSTING, $source?->posting?->id->toString());
        self::assertSame(self::ORIGINAL_ENTRY, $source?->journalEntry?->id()->toString());
        self::assertCount(2, $source?->journalEntry?->lines() ?? []);
        self::assertSame(self::ALLOCATION, $source?->settlements[0]->paymentAllocationId->toString());
        self::assertSame(self::APPLIED, $source?->settlements[0]->settlement->id()->toString());
        self::assertNull($this->app->make(GetBankTransactionReversalReadiness::class)->execute($this->admin(self::B), $this->tx()));
        self::assertSame($before, $this->financialCounts());

        DB::table('journals')->where('id', $this->id(30))->update(['status' => 'inactive']);
        DB::table('ledger_accounts')->whereIn('id', [$this->id(31), $this->id(32)])->update(['status' => 'inactive']);
        self::assertSame(BankTransactionReversalEligibilityStatus::Eligible, $this->eligibility()->execute($this->admin(self::A), $this->tx()));
        DB::table('open_item_settlements')->insert(['id' => $this->id(35), 'administration_id' => self::A, 'open_item_id' => self::ITEM, 'payment_allocation_id' => null, 'effective_date' => '2026-08-27', 'amount' => '10', 'currency' => 'EUR', 'source_journal_entry_id' => self::ORIGINAL_ENTRY, 'type' => 'applied', 'reversed_settlement_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        self::assertSame(BankTransactionReversalEligibilityStatus::Eligible, $this->eligibility()->execute($this->admin(self::A), $this->tx()));
        DB::table('open_item_matches')->insert(['id' => $this->id(33), 'administration_id' => self::A, 'debit_open_item_id' => self::ITEM, 'credit_open_item_id' => $this->id(34), 'amount' => '10', 'currency' => 'EUR', 'occurred_on' => '2026-08-27', 'source_journal_entry_id' => self::ORIGINAL_ENTRY, 'created_at' => now(), 'updated_at' => now()]);
        self::assertSame(BankTransactionReversalEligibilityStatus::Eligible, $this->eligibility()->execute($this->admin(self::A), $this->tx()));
    }

    public function test_non_posted_states_and_partial_prior_reversal_are_typed(): void
    {
        foreach (['draft', 'finalized', 'cancelled'] as $status) {
            DB::table('bank_transactions')->where('id', self::TX)->update(['status' => $status]);
            self::assertSame(BankTransactionReversalEligibilityStatus::NotPosted, $this->eligibility()->execute($this->admin(self::A), $this->tx()));
        }
        DB::table('bank_transactions')->where('id', self::TX)->update(['status' => 'posted']);
        DB::table('open_item_settlements')->insert(['id' => self::SETTLEMENT_REVERSAL, 'administration_id' => self::A, 'open_item_id' => self::ITEM, 'payment_allocation_id' => null, 'effective_date' => '2026-08-28', 'amount' => '100', 'currency' => 'EUR', 'source_journal_entry_id' => self::REVERSAL_ENTRY, 'type' => 'reversal', 'reversed_settlement_id' => self::APPLIED, 'created_at' => now(), 'updated_at' => now()]);
        self::assertSame(BankTransactionReversalEligibilityStatus::FinancialStateInvalid, $this->eligibility()->execute($this->admin(self::A), $this->tx()));
    }

    public function test_reversal_and_settlement_link_roundtrip_uniqueness_and_restrict_constraints(): void
    {
        $reversal = $this->reversal();
        $repository = $this->app->make(BankTransactionReversalRepository::class);
        self::assertTrue($repository->appendReversal($reversal));
        self::assertFalse($repository->appendReversal($reversal));
        $actual = $repository->findByOriginal($this->admin(self::A), $this->tx(), true);
        self::assertSame(self::REVERSAL, $actual?->id->toString());
        self::assertSame(self::POSTING, $actual?->originalBankTransactionPostingId->toString());
        self::assertSame(self::ORIGINAL_ENTRY, $actual?->originalJournalEntryId->toString());
        self::assertSame(self::REVERSAL_ENTRY, $actual?->reversalJournalEntryId->toString());
        self::assertSame('Correction required', $actual?->reason->value());
        self::assertSame('2026-08-28', $actual?->reversalPostingDate->value()->format('Y-m-d'));
        self::assertSame(self::USER, $actual?->reversedBy->toString());

        DB::table('open_item_settlements')->insert(['id' => self::SETTLEMENT_REVERSAL, 'administration_id' => self::A, 'open_item_id' => self::ITEM, 'payment_allocation_id' => null, 'effective_date' => '2026-08-28', 'amount' => '100', 'currency' => 'EUR', 'source_journal_entry_id' => self::REVERSAL_ENTRY, 'type' => 'reversal', 'reversed_settlement_id' => self::APPLIED, 'created_at' => now(), 'updated_at' => now()]);
        $links = $this->app->make(BankTransactionSettlementReversalLinkRepository::class);
        $link = new BankTransactionSettlementReversalLink(new BankTransactionSettlementReversalLinkId(new Uuid($this->id(40))), $this->admin(self::A), new BankTransactionReversalId(new Uuid(self::REVERSAL)), new PaymentAllocationId(new Uuid(self::ALLOCATION)), new OpenItemId(new Uuid(self::ITEM)), new OpenItemSettlementId(new Uuid(self::APPLIED)), new OpenItemSettlementId(new Uuid(self::SETTLEMENT_REVERSAL)));
        self::assertTrue($links->appendLink($link));
        self::assertFalse($links->appendLink($link));
        self::assertSame(self::SETTLEMENT_REVERSAL, $links->findByReversal($this->admin(self::A), new BankTransactionReversalId(new Uuid(self::REVERSAL)), true)[0]->reversalOpenItemSettlementId->toString());
        self::assertSame(BankTransactionReversalEligibilityStatus::AlreadyReversed, $this->eligibility()->execute($this->admin(self::A), $this->tx()));

        foreach ([['bank_transactions', self::TX], ['journal_entries', self::ORIGINAL_ENTRY], ['open_item_settlements', self::APPLIED], ['payment_allocations', self::ALLOCATION]] as [$table, $id]) {
            try {
                DB::table($table)->where('id', $id)->delete();
                self::fail("{$table} delete was not restricted.");
            } catch (QueryException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_cross_tenant_and_orphan_reversal_rows_are_rejected(): void
    {
        $values = ['id' => $this->id(50), 'administration_id' => self::B, 'original_bank_transaction_id' => self::TX, 'original_bank_transaction_posting_id' => self::POSTING, 'original_journal_entry_id' => self::ORIGINAL_ENTRY, 'reversal_journal_entry_id' => self::REVERSAL_ENTRY, 'reversal_posting_date' => '2026-08-28', 'reason' => 'Cross tenant', 'reversed_by' => self::USER, 'reversed_at' => now(), 'created_at' => now()];
        $this->expectException(QueryException::class);
        DB::table('bank_transaction_reversals')->insert($values);
    }

    private function fixtures(): void
    {
        $now = now();
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Actor', 'email' => 'b3@example.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([self::A, self::B] as $n => $admin) {
            DB::table('administrations')->insert(['id' => $admin, 'code' => 'B3'.$n, 'name' => 'B3 '.$n, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('relations')->insert(['id' => $this->id(20), 'administration_id' => self::A, 'code' => 'REL', 'display_name' => 'Relation', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[31, '1100', 'Bank', 'asset'], [32, '1300', 'AR', 'asset']] as [$n, $code, $name, $type]) {
            DB::table('ledger_accounts')->insert(['id' => $this->id($n), 'administration_id' => self::A, 'code' => $code, 'name' => $name, 'type' => $type, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('journals')->insert(['id' => $this->id(30), 'administration_id' => self::A, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'bank', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([self::ORIGINAL_ENTRY, self::REVERSAL_ENTRY] as $entry) {
            DB::table('journal_entries')->insert(['id' => $entry, 'administration_id' => self::A, 'journal_id' => $this->id(30), 'posting_date' => '2026-08-27', 'reference' => $entry, 'status' => 'posted', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([[60, 31, '100', null], [61, 32, null, '100']] as [$n, $account, $debit, $credit]) {
            DB::table('journal_entry_lines')->insert(['id' => $this->id($n), 'administration_id' => self::A, 'journal_entry_id' => self::ORIGINAL_ENTRY, 'ledger_account_id' => $this->id($account), 'debit_amount' => $debit, 'credit_amount' => $credit, 'currency' => 'EUR', 'description' => 'Original', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('open_items')->insert(['id' => self::ITEM, 'administration_id' => self::A, 'relation_id' => $this->id(20), 'journal_entry_id' => self::ORIGINAL_ENTRY, 'control_ledger_account_id' => $this->id(32), 'open_item_type' => 'receivable', 'side' => 'debit', 'original_amount' => '200', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('open_items')->insert(['id' => $this->id(34), 'administration_id' => self::A, 'relation_id' => $this->id(20), 'journal_entry_id' => self::ORIGINAL_ENTRY, 'control_ledger_account_id' => $this->id(32), 'open_item_type' => 'receivable', 'side' => 'credit', 'original_amount' => '10', 'currency' => 'EUR', 'opened_on' => '2026-08-01', 'due_date' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('administration_bank_accounts')->insert(['id' => $this->id(21), 'administration_id' => self::A, 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_holder' => 'Holder', 'label' => 'Main', 'currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('bank_transactions')->insert(['id' => self::TX, 'administration_id' => self::A, 'administration_bank_account_id' => $this->id(21), 'transaction_date' => '2026-08-26', 'amount' => '100', 'currency' => 'EUR', 'reference' => 'PAY', 'description' => 'Payment', 'status' => 'posted', 'created_by' => self::USER, 'created_at' => $now, 'finalized_by' => self::USER, 'finalized_at' => $now, 'posted_by' => self::USER, 'posted_at' => $now]);
        DB::table('payments')->insert(['id' => self::PAYMENT, 'administration_id' => self::A, 'bank_transaction_id' => self::TX, 'relation_id' => $this->id(20), 'type' => 'customer_receipt', 'amount' => '100', 'currency' => 'EUR']);
        DB::table('payment_allocations')->insert(['id' => self::ALLOCATION, 'administration_id' => self::A, 'payment_id' => self::PAYMENT, 'open_item_id' => self::ITEM, 'amount' => '100', 'currency' => 'EUR', 'open_item_type' => 'receivable', 'open_item_side' => 'debit', 'relation_id_snapshot' => $this->id(20), 'control_ledger_account_id_snapshot' => $this->id(32)]);
        DB::table('bank_transaction_postings')->insert(['id' => self::POSTING, 'administration_id' => self::A, 'bank_transaction_id' => self::TX, 'journal_entry_id' => self::ORIGINAL_ENTRY, 'posting_date' => '2026-08-27', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('open_item_settlements')->insert(['id' => self::APPLIED, 'administration_id' => self::A, 'open_item_id' => self::ITEM, 'payment_allocation_id' => self::ALLOCATION, 'effective_date' => '2026-08-27', 'amount' => '100', 'currency' => 'EUR', 'source_journal_entry_id' => self::ORIGINAL_ENTRY, 'type' => 'applied', 'reversed_settlement_id' => null, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function reversal(): BankTransactionReversal
    {
        return new BankTransactionReversal(new BankTransactionReversalId(new Uuid(self::REVERSAL)), $this->admin(self::A), $this->tx(), new BankTransactionPostingId(new Uuid(self::POSTING)), new JournalEntryId(new Uuid(self::ORIGINAL_ENTRY)), new JournalEntryId(new Uuid(self::REVERSAL_ENTRY)), new PostingDate(new DateTimeImmutable('2026-08-28')), new BankTransactionReversalReason('Correction required'), new UserId(new Uuid(self::USER)), new DateTimeImmutable('2026-08-28T12:00:00+00:00'));
    }

    private function eligibility(): AssessBankTransactionReversalEligibility
    {
        return $this->app->make(AssessBankTransactionReversalEligibility::class);
    }

    private function financialCounts(): array
    {
        return [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('open_item_settlements')->count(), DB::table('open_item_matches')->count(), DB::table('open_items')->count(), DB::table('tax_postings')->count()];
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function tx(): BankTransactionId
    {
        return new BankTransactionId(new Uuid(self::TX));
    }

    private function id(int $number): string
    {
        return sprintf('b3100000-0000-4000-8000-%012d', $number);
    }
}
