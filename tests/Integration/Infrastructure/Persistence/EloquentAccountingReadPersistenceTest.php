<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Accounting\JournalEntryReadRepository;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\LedgerAccountStore;
use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentJournalEntryRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentLedgerAccountRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentAccountingReadPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    private EloquentLedgerAccountRepository $accounts;

    private EloquentJournalEntryRepository $entries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new EloquentLedgerAccountRepository;
        $this->entries = new EloquentJournalEntryRepository;
        $this->createAdministration(self::ADMINISTRATION_A, 'A');
        $this->createAdministration(self::ADMINISTRATION_B, 'B');
        $this->createJournal(self::ADMINISTRATION_A);
        $this->createJournal(self::ADMINISTRATION_B);
    }

    public function test_ledger_accounts_roundtrip_with_explicit_tenant_ownership(): void
    {
        $accountA = $this->account('30000000-0000-4000-8000-000000000001', '8000', LedgerAccountType::Revenue);
        $accountB = $this->account('30000000-0000-4000-8000-000000000002', '4000', LedgerAccountType::Expense);
        $this->accounts->save($this->administrationId(self::ADMINISTRATION_A), $accountA);
        $this->accounts->save($this->administrationId(self::ADMINISTRATION_B), $accountB);

        $readA = $this->accounts->findForAdministration($this->administrationId(self::ADMINISTRATION_A));

        self::assertCount(1, $readA);
        self::assertTrue($accountA->id()->equals($readA[0]->id()));
        self::assertSame('8000', $readA[0]->code()->toString());
        self::assertSame(LedgerAccountType::Revenue, $readA[0]->type());
        self::assertSame(LedgerAccountStatus::Active, $readA[0]->status());
    }

    public function test_a_ledger_account_identity_cannot_move_to_another_administration(): void
    {
        $account = $this->account('30000000-0000-4000-8000-000000000001', '8000', LedgerAccountType::Revenue);
        $this->accounts->save($this->administrationId(self::ADMINISTRATION_A), $account);

        $this->expectException(DomainException::class);
        $this->accounts->save($this->administrationId(self::ADMINISTRATION_B), $account);
    }

    public function test_it_appends_and_reconstitutes_exact_posted_financial_truth(): void
    {
        $this->seedAccounts(self::ADMINISTRATION_A);
        $entry = $this->entry('40000000-0000-4000-8000-000000000001', self::ADMINISTRATION_A, '2026-08-20', '100.12345678');

        $this->entries->append($entry);
        $read = $this->entries->findPostedForAdministrationAndPeriod(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->date('2026-08-01'),
            $this->date('2026-08-31'),
        );

        self::assertCount(1, $read);
        self::assertTrue($entry->id()->equals($read[0]->id()));
        self::assertTrue($entry->administrationId()->equals($read[0]->administrationId()));
        self::assertTrue($entry->journalId()->equals($read[0]->journalId()));
        self::assertTrue($entry->postingDate()->equals($read[0]->postingDate()));
        self::assertSame('Persistence 40000000', $read[0]->reference()->toString());
        self::assertSame(JournalEntryStatus::Posted, $read[0]->status());
        self::assertSame('100.12345678', $read[0]->lines()[0]->debit()?->amount());
        self::assertSame('EUR', $read[0]->lines()[0]->debit()?->currency()->code());
        self::assertSame('100.12345678', $read[0]->lines()[1]->credit()?->amount());

        $this->expectException(DomainException::class);
        $read[0]->addLine($this->line('50000000-0000-4000-8000-000000000099', true, '1'));
    }

    public function test_reads_are_tenant_scoped_posted_only_inclusive_and_deterministic(): void
    {
        $this->seedAccounts(self::ADMINISTRATION_A);
        $this->seedAccounts(self::ADMINISTRATION_B);
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000005', self::ADMINISTRATION_A, '2026-08-31'));
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000003', self::ADMINISTRATION_A, '2026-08-01'));
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000002', self::ADMINISTRATION_A, '2026-08-15'));
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000001', self::ADMINISTRATION_A, '2026-08-15'));
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000006', self::ADMINISTRATION_A, '2026-07-31'));
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000007', self::ADMINISTRATION_A, '2026-09-01'));
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000008', self::ADMINISTRATION_B, '2026-08-15'));
        JournalEntryRecord::query()->create([
            'id' => '40000000-0000-4000-8000-000000000009',
            'administration_id' => self::ADMINISTRATION_A,
            'journal_id' => '60000000-0000-4000-8000-000000000001',
            'posting_date' => '2026-08-15',
            'reference' => 'Draft fixture',
            'status' => JournalEntryStatus::Draft->value,
        ]);

        $read = $this->entries->findPostedForAdministrationAndPeriod(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->date('2026-08-01'),
            $this->date('2026-08-31'),
        );

        self::assertSame([
            '40000000-0000-4000-8000-000000000003',
            '40000000-0000-4000-8000-000000000001',
            '40000000-0000-4000-8000-000000000002',
            '40000000-0000-4000-8000-000000000005',
        ], array_map(static fn (JournalEntry $entry): string => $entry->id()->toString(), $read));
    }

    public function test_duplicate_entry_identity_is_rejected_without_overwrite(): void
    {
        $this->seedAccounts(self::ADMINISTRATION_A);
        $entry = $this->entry('40000000-0000-4000-8000-000000000001', self::ADMINISTRATION_A, '2026-08-20');
        $this->entries->append($entry);

        try {
            $this->entries->append($entry);
            self::fail('Expected duplicate append to fail.');
        } catch (DomainException) {
            self::assertSame(1, JournalEntryRecord::query()->count());
            self::assertSame(2, JournalEntryLineRecord::query()->count());
        }
    }

    public function test_cross_tenant_ledger_account_link_is_rejected_atomically(): void
    {
        $this->seedAccounts(self::ADMINISTRATION_B);
        $entry = $this->entry('40000000-0000-4000-8000-000000000001', self::ADMINISTRATION_A, '2026-08-20');

        try {
            $this->entries->append($entry);
            self::fail('Expected cross-tenant append to fail.');
        } catch (DomainException) {
            self::assertSame(0, JournalEntryRecord::query()->count());
            self::assertSame(0, JournalEntryLineRecord::query()->count());
        }
    }

    public function test_duplicate_line_identity_rolls_back_entry_and_all_lines(): void
    {
        $this->seedAccounts(self::ADMINISTRATION_A);
        $first = $this->entry('40000000-0000-4000-8000-000000000001', self::ADMINISTRATION_A, '2026-08-20');
        $this->entries->append($first);
        $second = $this->entry('40000000-0000-4000-8000-000000000002', self::ADMINISTRATION_A, '2026-08-21');
        JournalEntryLineRecord::query()->where('journal_entry_id', $first->id()->toString())->firstOrFail()->update([
            'id' => $second->lines()[0]->id()->toString(),
        ]);

        try {
            $this->entries->append($second);
            self::fail('Expected duplicate line identity to fail.');
        } catch (QueryException) {
            self::assertFalse(JournalEntryRecord::query()->whereKey($second->id()->toString())->exists());
            self::assertSame(2, JournalEntryLineRecord::query()->count());
        }
    }

    public function test_database_rejects_a_cross_tenant_ledger_account_link(): void
    {
        $this->seedAccounts(self::ADMINISTRATION_B);
        JournalEntryRecord::query()->create([
            'id' => '40000000-0000-4000-8000-000000000001',
            'administration_id' => self::ADMINISTRATION_A,
            'journal_id' => '60000000-0000-4000-8000-000000000001',
            'posting_date' => '2026-08-20',
            'reference' => 'Constraint fixture',
            'status' => JournalEntryStatus::Posted->value,
        ]);

        $this->expectException(QueryException::class);
        JournalEntryLineRecord::query()->create([
            'id' => '50000000-0000-4000-8000-000000000001',
            'administration_id' => self::ADMINISTRATION_A,
            'journal_entry_id' => '40000000-0000-4000-8000-000000000001',
            'ledger_account_id' => '30000000-0000-4000-8000-000000000003',
            'debit_amount' => '100',
            'credit_amount' => null,
            'currency' => 'EUR',
            'description' => 'Invalid cross-tenant line',
        ]);
    }

    public function test_financial_foreign_keys_restrict_deletion(): void
    {
        $this->seedAccounts(self::ADMINISTRATION_A);
        $this->entries->append($this->entry('40000000-0000-4000-8000-000000000001', self::ADMINISTRATION_A, '2026-08-20'));

        $this->expectException(QueryException::class);
        LedgerAccountRecord::query()->findOrFail('30000000-0000-4000-8000-000000000001')->delete();
    }

    public function test_application_contracts_resolve_to_accounting_adapters(): void
    {
        self::assertInstanceOf(EloquentLedgerAccountRepository::class, $this->app->make(LedgerAccountReadRepository::class));
        self::assertInstanceOf(EloquentLedgerAccountRepository::class, $this->app->make(LedgerAccountStore::class));
        self::assertInstanceOf(EloquentJournalEntryRepository::class, $this->app->make(JournalEntryReadRepository::class));
        self::assertInstanceOf(EloquentJournalEntryRepository::class, $this->app->make(JournalEntryStore::class));
        self::assertFalse(method_exists(JournalEntryStore::class, 'update'));
        self::assertFalse(method_exists(JournalEntryStore::class, 'delete'));
    }

    private function seedAccounts(string $administration): void
    {
        $this->accounts->save($this->administrationId($administration), $this->account(
            $administration === self::ADMINISTRATION_A
                ? '30000000-0000-4000-8000-000000000001'
                : '30000000-0000-4000-8000-000000000003',
            '1000',
            LedgerAccountType::Asset,
        ));
        $this->accounts->save($this->administrationId($administration), $this->account(
            $administration === self::ADMINISTRATION_A
                ? '30000000-0000-4000-8000-000000000002'
                : '30000000-0000-4000-8000-000000000004',
            '8000',
            LedgerAccountType::Revenue,
        ));
    }

    private function createJournal(string $administration): void
    {
        JournalRecord::query()->create([
            'id' => $this->journalId($administration),
            'administration_id' => $administration,
            'code' => 'TEST',
            'name' => 'Accounting test journal',
            'type' => 'general',
            'status' => 'active',
        ]);
    }

    private function journalId(string $administration): string
    {
        return $administration === self::ADMINISTRATION_A
            ? '60000000-0000-4000-8000-000000000001'
            : '61000000-0000-4000-8000-000000000001';
    }

    private function entry(string $id, string $administration, string $date, string $amount = '100'): JournalEntry
    {
        $isA = $administration === self::ADMINISTRATION_A;

        return JournalEntry::reconstitute(
            new JournalEntryId(new Uuid($id)),
            $this->administrationId($administration),
            new JournalId(new Uuid($this->journalId($administration))),
            $this->date($date),
            new JournalEntryReference('Persistence '.substr($id, 0, 8)),
            JournalEntryStatus::Posted,
            [
                $this->line('50000000-0000-4000-8000-'.substr($id, 24), true, $amount, $isA ? 1 : 3),
                $this->line('51000000-0000-4000-8000-'.substr($id, 24), false, $amount, $isA ? 2 : 4),
            ],
        );
    }

    private function line(string $id, bool $debit, string $amount, int $accountSuffix = 1): JournalEntryLine
    {
        $money = new Money($amount, new Currency('EUR'));

        return new JournalEntryLine(
            new JournalEntryLineId(new Uuid($id)),
            new LedgerAccountId(new Uuid(sprintf('30000000-0000-4000-8000-%012d', $accountSuffix))),
            $debit ? $money : null,
            $debit ? null : $money,
            $debit ? 'Debit' : 'Credit',
        );
    }

    private function account(string $id, string $code, LedgerAccountType $type): LedgerAccount
    {
        return new LedgerAccount(
            new LedgerAccountId(new Uuid($id)),
            new LedgerAccountCode($code),
            new LedgerAccountName('Account '.$code),
            $type,
            LedgerAccountStatus::Active,
        );
    }

    private function createAdministration(string $id, string $code): void
    {
        AdministrationRecord::query()->create([
            'id' => $id,
            'code' => $code,
            'name' => 'Administration '.$code,
            'base_currency' => 'EUR',
            'status' => 'active',
        ]);
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function date(string $date): PostingDate
    {
        return new PostingDate(new DateTimeImmutable($date));
    }
}
