<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reporting;

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
use App\Domain\Reporting\TrialBalance;
use App\Domain\Reporting\TrialBalanceLine;
use App\Domain\Reporting\TrialBalanceResult;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class TrialBalanceTest extends TestCase
{
    private const string ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000001';

    private const string OTHER_ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000002';

    private const string BANK_ACCOUNT_ID = '20000000-0000-4000-8000-000000000001';

    private const string REVENUE_ACCOUNT_ID = '20000000-0000-4000-8000-000000000002';

    private int $identitySequence = 1;

    public function test_empty_dataset_returns_zero_totals_in_the_explicit_currency(): void
    {
        $currency = new Currency('EUR');

        $result = $this->calculate([], [], currency: $currency);

        self::assertSame([], $result->lines());
        self::assertTrue($result->totalDebit()->equals(Money::zero($currency)));
        self::assertTrue($result->totalCredit()->equals(Money::zero($currency)));
        self::assertTrue($result->isBalanced());
        self::assertTrue($result->totalDebit()->currency()->equals($currency));
    }

    public function test_reporting_context_is_passed_through_unchanged(): void
    {
        $administrationId = $this->administrationId(self::ADMINISTRATION_ID);
        $startDate = new DateTimeImmutable('2026-07-01');
        $endDate = new DateTimeImmutable('2026-07-31');
        $from = new PostingDate($startDate);
        $to = new PostingDate($endDate);
        $currency = new Currency('EUR');

        $result = $this->calculate(
            [],
            [],
            administrationId: $administrationId,
            from: $from,
            to: $to,
            currency: $currency,
        );

        self::assertSame($administrationId, $result->administrationId());
        self::assertSame($startDate, $result->startDate());
        self::assertSame($endDate, $result->endDate());
        self::assertSame($currency, $result->currency());
    }

    public function test_result_rejects_an_invalid_reporting_period(): void
    {
        $currency = new Currency('EUR');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Trial balance result start date cannot be after end date.');

        new TrialBalanceResult(
            [],
            Money::zero($currency),
            Money::zero($currency),
            $this->administrationId(self::ADMINISTRATION_ID),
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-07-31'),
            $currency,
        );
    }

    public function test_one_balanced_posted_entry_is_totalled_per_ledger_account(): void
    {
        $currency = new Currency('EUR');
        $bank = $this->account(self::BANK_ACCOUNT_ID, '1000', LedgerAccountType::Asset);
        $revenue = $this->account(self::REVENUE_ACCOUNT_ID, '8000', LedgerAccountType::Revenue);
        $entry = $this->entry('2026-07-15', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
            $this->debit(self::BANK_ACCOUNT_ID, '125.50', $currency),
            $this->credit(self::REVENUE_ACCOUNT_ID, '125.50', $currency),
        ]);

        $result = $this->calculate([$bank, $revenue], [$entry], currency: $currency);

        self::assertCount(2, $result->lines());
        $bankLine = $this->line($result->lines(), self::BANK_ACCOUNT_ID);
        $revenueLine = $this->line($result->lines(), self::REVENUE_ACCOUNT_ID);
        self::assertSame('125.5', $bankLine->totalDebit()->amount());
        self::assertSame('0', $bankLine->totalCredit()->amount());
        self::assertSame('125.5', $bankLine->balance()->amount());
        self::assertSame('0', $revenueLine->totalDebit()->amount());
        self::assertSame('125.5', $revenueLine->totalCredit()->amount());
        self::assertSame('-125.5', $revenueLine->balance()->amount());
        self::assertSame('125.5', $result->totalDebit()->amount());
        self::assertSame('125.5', $result->totalCredit()->amount());
        self::assertTrue($result->isBalanced());
    }

    public function test_multiple_entries_are_accumulated_exactly_across_accounts(): void
    {
        $currency = new Currency('EUR');
        $bank = $this->account(self::BANK_ACCOUNT_ID, '1000', LedgerAccountType::Asset);
        $revenue = $this->account(self::REVENUE_ACCOUNT_ID, '8000', LedgerAccountType::Revenue);
        $entries = [
            $this->balancedEntry('2026-07-10', '10.12345678', $currency),
            $this->balancedEntry('2026-07-20', '20.87654322', $currency),
        ];

        $result = $this->calculate([$bank, $revenue], $entries, currency: $currency);

        self::assertSame('31', $this->line($result->lines(), self::BANK_ACCOUNT_ID)->totalDebit()->amount());
        self::assertSame('31', $this->line($result->lines(), self::REVENUE_ACCOUNT_ID)->totalCredit()->amount());
        self::assertSame('31', $result->totalDebit()->amount());
        self::assertSame('31', $result->totalCredit()->amount());
    }

    public function test_draft_entries_are_ignored(): void
    {
        $result = $this->filteredResult(
            $this->entry('2026-07-15', JournalEntryStatus::Draft, self::ADMINISTRATION_ID, $this->balancedLines('50')),
        );

        $this->assertZeroResult($result->lines(), $result->totalDebit(), $result->totalCredit());
    }

    public function test_entries_for_another_administration_are_ignored(): void
    {
        $result = $this->filteredResult(
            $this->entry('2026-07-15', JournalEntryStatus::Posted, self::OTHER_ADMINISTRATION_ID, $this->balancedLines('50')),
        );

        $this->assertZeroResult($result->lines(), $result->totalDebit(), $result->totalCredit());
    }

    public function test_entries_before_the_start_date_are_ignored(): void
    {
        $result = $this->filteredResult(
            $this->entry('2026-06-30', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, $this->balancedLines('50')),
        );

        $this->assertZeroResult($result->lines(), $result->totalDebit(), $result->totalCredit());
    }

    public function test_entries_after_the_end_date_are_ignored(): void
    {
        $result = $this->filteredResult(
            $this->entry('2026-08-01', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, $this->balancedLines('50')),
        );

        $this->assertZeroResult($result->lines(), $result->totalDebit(), $result->totalCredit());
    }

    public function test_start_and_end_dates_are_inclusive(): void
    {
        $currency = new Currency('EUR');
        $entries = [
            $this->balancedEntry('2026-07-01', '40', $currency),
            $this->balancedEntry('2026-07-31', '60', $currency),
        ];

        $result = $this->calculate($this->accounts(), $entries, currency: $currency);

        self::assertSame('100', $result->totalDebit()->amount());
        self::assertSame('100', $result->totalCredit()->amount());
        self::assertTrue($result->isBalanced());
    }

    public function test_currency_remains_consistent_for_every_amount(): void
    {
        $currency = new Currency('USD');
        $result = $this->calculate(
            $this->accounts(),
            [$this->balancedEntry('2026-07-15', '75', $currency)],
            currency: $currency,
        );

        foreach ($result->lines() as $line) {
            self::assertTrue($line->totalDebit()->currency()->equals($currency));
            self::assertTrue($line->totalCredit()->currency()->equals($currency));
            self::assertTrue($line->balance()->currency()->equals($currency));
        }

        self::assertTrue($result->totalDebit()->currency()->equals($currency));
        self::assertTrue($result->totalCredit()->currency()->equals($currency));
    }

    public function test_mixed_currency_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Money amounts must use the same currency.');

        $this->calculate(
            $this->accounts(),
            [$this->balancedEntry('2026-07-15', '75', new Currency('USD'))],
            currency: new Currency('EUR'),
        );
    }

    public function test_input_aggregates_are_not_mutated(): void
    {
        $currency = new Currency('EUR');
        $accounts = $this->accounts();
        $entry = $this->balancedEntry('2026-07-15', '25', $currency);
        $accountState = array_map(
            static fn (LedgerAccount $account): array => [$account->id(), $account->name(), $account->status()],
            $accounts,
        );
        $entryLines = $entry->lines();
        $entryStatus = $entry->status();

        $this->calculate($accounts, [$entry], currency: $currency);

        foreach ($accounts as $index => $account) {
            self::assertSame($accountState[$index][0], $account->id());
            self::assertSame($accountState[$index][1], $account->name());
            self::assertSame($accountState[$index][2], $account->status());
        }
        self::assertSame($entryLines, $entry->lines());
        self::assertSame($entryStatus, $entry->status());
    }

    public function test_all_calculated_amounts_remain_decimal_strings_without_floats(): void
    {
        $currency = new Currency('EUR');
        $result = $this->calculate(
            $this->accounts(),
            [$this->balancedEntry('2026-07-15', '0.12345678', $currency)],
            currency: $currency,
        );

        foreach ($result->lines() as $line) {
            self::assertIsString($line->totalDebit()->amount());
            self::assertIsString($line->totalCredit()->amount());
            self::assertIsString($line->balance()->amount());
        }
        self::assertIsString($result->totalDebit()->amount());
        self::assertIsString($result->totalCredit()->amount());
    }

    private function filteredResult(JournalEntry $entry): TrialBalanceResult
    {
        return $this->calculate($this->accounts(), [$entry], currency: new Currency('EUR'));
    }

    /**
     * @param  list<LedgerAccount>  $accounts
     * @param  list<JournalEntry>  $entries
     */
    private function calculate(
        array $accounts,
        array $entries,
        ?AdministrationId $administrationId = null,
        ?PostingDate $from = null,
        ?PostingDate $to = null,
        ?Currency $currency = null,
    ): TrialBalanceResult {
        return (new TrialBalance)->calculate(
            $accounts,
            $entries,
            $administrationId ?? $this->administrationId(self::ADMINISTRATION_ID),
            $from ?? new PostingDate(new DateTimeImmutable('2026-07-01')),
            $to ?? new PostingDate(new DateTimeImmutable('2026-07-31')),
            $currency ?? new Currency('EUR'),
        );
    }

    /** @return list<LedgerAccount> */
    private function accounts(): array
    {
        return [
            $this->account(self::BANK_ACCOUNT_ID, '1000', LedgerAccountType::Asset),
            $this->account(self::REVENUE_ACCOUNT_ID, '8000', LedgerAccountType::Revenue),
        ];
    }

    private function account(string $id, string $code, LedgerAccountType $type): LedgerAccount
    {
        return new LedgerAccount(
            $this->ledgerAccountId($id),
            new LedgerAccountCode($code),
            new LedgerAccountName('Account '.$code),
            $type,
            LedgerAccountStatus::Active,
        );
    }

    private function balancedEntry(string $date, string $amount, Currency $currency): JournalEntry
    {
        return $this->entry(
            $date,
            JournalEntryStatus::Posted,
            self::ADMINISTRATION_ID,
            $this->balancedLines($amount, $currency),
        );
    }

    /** @return list<JournalEntryLine> */
    private function balancedLines(string $amount, ?Currency $currency = null): array
    {
        $currency ??= new Currency('EUR');

        return [
            $this->debit(self::BANK_ACCOUNT_ID, $amount, $currency),
            $this->credit(self::REVENUE_ACCOUNT_ID, $amount, $currency),
        ];
    }

    /** @param list<JournalEntryLine> $lines */
    private function entry(
        string $date,
        JournalEntryStatus $status,
        string $administrationId,
        array $lines,
    ): JournalEntry {
        $entry = new JournalEntry(
            new JournalEntryId($this->nextUuid()),
            $this->administrationId($administrationId),
            new JournalId($this->nextUuid()),
            new PostingDate(new DateTimeImmutable($date)),
            new JournalEntryReference('Trial balance source'),
            JournalEntryStatus::Draft,
        );

        foreach ($lines as $line) {
            $entry->addLine($line);
        }

        if ($status === JournalEntryStatus::Posted) {
            $entry->post();
        }

        return $entry;
    }

    private function debit(string $accountId, string $amount, Currency $currency): JournalEntryLine
    {
        return $this->entryLine($accountId, new Money($amount, $currency), null);
    }

    private function credit(string $accountId, string $amount, Currency $currency): JournalEntryLine
    {
        return $this->entryLine($accountId, null, new Money($amount, $currency));
    }

    private function entryLine(string $accountId, ?Money $debit, ?Money $credit): JournalEntryLine
    {
        return new JournalEntryLine(
            new JournalEntryLineId($this->nextUuid()),
            $this->ledgerAccountId($accountId),
            $debit,
            $credit,
            'Trial balance line',
        );
    }

    /** @param list<TrialBalanceLine> $lines */
    private function line(array $lines, string $accountId): TrialBalanceLine
    {
        foreach ($lines as $line) {
            if ($line->ledgerAccountId()->toString() === $accountId) {
                return $line;
            }
        }

        self::fail('Expected trial balance line was not found.');
    }

    /**
     * @param  list<TrialBalanceLine>  $lines
     */
    private function assertZeroResult(array $lines, Money $totalDebit, Money $totalCredit): void
    {
        self::assertCount(2, $lines);
        foreach ($lines as $line) {
            self::assertTrue($line->totalDebit()->isZero());
            self::assertTrue($line->totalCredit()->isZero());
            self::assertTrue($line->balance()->isZero());
        }
        self::assertTrue($totalDebit->isZero());
        self::assertTrue($totalCredit->isZero());
    }

    private function administrationId(string $uuid): AdministrationId
    {
        return new AdministrationId(new Uuid($uuid));
    }

    private function ledgerAccountId(string $uuid): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid($uuid));
    }

    private function nextUuid(): Uuid
    {
        return new Uuid(sprintf('30000000-0000-4000-8000-%012d', $this->identitySequence++));
    }
}
