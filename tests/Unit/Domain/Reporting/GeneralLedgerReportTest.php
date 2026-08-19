<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reporting;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Reporting\GeneralLedgerReport;
use App\Domain\Reporting\GeneralLedgerResult;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GeneralLedgerReportTest extends TestCase
{
    private const string ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000001';

    private const string OTHER_ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000002';

    private const string ACCOUNT_ID = '20000000-0000-4000-8000-000000000001';

    private const string OTHER_ACCOUNT_ID = '20000000-0000-4000-8000-000000000002';

    private int $identitySequence = 1;

    public function test_empty_dataset_returns_explicit_context_and_zero_movement(): void
    {
        $administrationId = $this->administrationId(self::ADMINISTRATION_ID);
        $currency = new Currency('EUR');
        $startDate = new DateTimeImmutable('2026-07-01');
        $endDate = new DateTimeImmutable('2026-07-31');

        $result = $this->generate([], $administrationId, $currency, $startDate, $endDate);

        self::assertSame([], $result->lines());
        self::assertSame($administrationId, $result->administrationId());
        self::assertSame($currency, $result->currency());
        self::assertSame($startDate, $result->startDate());
        self::assertSame($endDate, $result->endDate());
        self::assertTrue($result->totalDebit()->isZero());
        self::assertTrue($result->totalCredit()->isZero());
        self::assertTrue($result->closingMovementBalance()->isZero());
    }

    public function test_one_posted_entry_exposes_source_metadata_and_debit_line(): void
    {
        $currency = new Currency('EUR');
        $entry = $this->entry('2026-07-15', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
            $this->debitLine(self::ACCOUNT_ID, '25', $currency),
        ]);

        $result = $this->generate([$entry], currency: $currency);
        $line = $result->lines()[0];

        self::assertSame($entry->postingDate(), $line->postingDate());
        self::assertSame($entry->id(), $line->journalEntryId());
        self::assertSame($entry->lines()[0]->id(), $line->journalEntryLineId());
        self::assertSame($entry->journalId(), $line->journalId());
        self::assertSame($entry->reference(), $line->reference());
        self::assertSame($entry->lines()[0]->ledgerAccountId(), $line->ledgerAccountId());
        self::assertSame('25', $line->debit()->amount());
        self::assertSame('0', $line->credit()->amount());
        self::assertSame('25', $line->runningBalance()->amount());
    }

    public function test_credit_line_uses_zero_debit_and_negative_period_movement(): void
    {
        $currency = new Currency('EUR');
        $entry = $this->entry('2026-07-15', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
            $this->creditLine(self::ACCOUNT_ID, '12.5', $currency),
        ]);

        $line = $this->generate([$entry], currency: $currency)->lines()[0];

        self::assertSame('0', $line->debit()->amount());
        self::assertSame('12.5', $line->credit()->amount());
        self::assertSame('-12.5', $line->runningBalance()->amount());
    }

    public function test_multiple_entries_calculate_running_balance_and_totals(): void
    {
        $currency = new Currency('EUR');
        $entries = [
            $this->entry('2026-07-10', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
                $this->debitLine(self::ACCOUNT_ID, '100.12345678', $currency),
            ]),
            $this->entry('2026-07-20', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
                $this->creditLine(self::ACCOUNT_ID, '40.02345678', $currency),
                $this->debitLine(self::OTHER_ACCOUNT_ID, '5', $currency),
            ]),
        ];

        $result = $this->generate($entries, currency: $currency);

        self::assertSame(['100.12345678', '60.1', '65.1'], array_map(
            static fn ($line): string => $line->runningBalance()->amount(),
            $result->lines(),
        ));
        self::assertSame('105.12345678', $result->totalDebit()->amount());
        self::assertSame('40.02345678', $result->totalCredit()->amount());
        self::assertSame('65.1', $result->closingMovementBalance()->amount());
    }

    public function test_draft_entry_is_ignored(): void
    {
        $result = $this->generate([$this->entryWithDebit('2026-07-15', JournalEntryStatus::Draft)]);

        $this->assertEmptyMovement($result);
    }

    public function test_entry_for_another_administration_is_ignored(): void
    {
        $entry = $this->entryWithDebit(
            '2026-07-15',
            JournalEntryStatus::Posted,
            self::OTHER_ADMINISTRATION_ID,
        );

        $this->assertEmptyMovement($this->generate([$entry]));
    }

    public function test_entry_before_period_is_ignored(): void
    {
        $this->assertEmptyMovement($this->generate([$this->entryWithDebit('2026-06-30')]));
    }

    public function test_entry_after_period_is_ignored(): void
    {
        $this->assertEmptyMovement($this->generate([$this->entryWithDebit('2026-08-01')]));
    }

    public function test_period_boundaries_are_inclusive(): void
    {
        $result = $this->generate([
            $this->entryWithDebit('2026-07-01'),
            $this->entryWithDebit('2026-07-31'),
        ]);

        self::assertCount(2, $result->lines());
        self::assertSame('20', $result->totalDebit()->amount());
    }

    public function test_optional_ledger_account_filter_selects_only_requested_account(): void
    {
        $currency = new Currency('EUR');
        $entry = $this->entry('2026-07-15', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
            $this->debitLine(self::ACCOUNT_ID, '10', $currency),
            $this->creditLine(self::OTHER_ACCOUNT_ID, '10', $currency),
        ]);

        $result = $this->generate(
            [$entry],
            currency: $currency,
            ledgerAccountId: $this->ledgerAccountId(self::OTHER_ACCOUNT_ID),
        );

        self::assertCount(1, $result->lines());
        self::assertSame(self::OTHER_ACCOUNT_ID, $result->lines()[0]->ledgerAccountId()->toString());
        self::assertSame('0', $result->totalDebit()->amount());
        self::assertSame('10', $result->totalCredit()->amount());
        self::assertSame('-10', $result->closingMovementBalance()->amount());
    }

    public function test_same_date_is_sorted_by_entry_id_then_line_id_independent_of_input_order(): void
    {
        $currency = new Currency('EUR');
        $laterEntryId = '30000000-0000-4000-8000-000000000002';
        $earlierEntryId = '30000000-0000-4000-8000-000000000001';
        $laterLineId = '40000000-0000-4000-8000-000000000002';
        $earlierLineId = '40000000-0000-4000-8000-000000000001';
        $laterEntry = $this->entry('2026-07-15', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
            $this->debitLine(self::ACCOUNT_ID, '2', $currency, $laterLineId),
        ], $laterEntryId);
        $earlierEntry = $this->entry('2026-07-15', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
            $this->debitLine(self::ACCOUNT_ID, '10', $currency, $laterLineId),
            $this->creditLine(self::ACCOUNT_ID, '3', $currency, $earlierLineId),
        ], $earlierEntryId);

        $result = $this->generate([$laterEntry, $earlierEntry], currency: $currency);

        self::assertSame(
            [$earlierEntryId, $earlierEntryId, $laterEntryId],
            array_map(static fn ($line): string => $line->journalEntryId()->toString(), $result->lines()),
        );
        self::assertSame(
            [$earlierLineId, $laterLineId, $laterLineId],
            array_map(static fn ($line): string => $line->journalEntryLineId()->toString(), $result->lines()),
        );
        self::assertSame(['-3', '7', '9'], array_map(
            static fn ($line): string => $line->runningBalance()->amount(),
            $result->lines(),
        ));
    }

    public function test_earlier_posting_date_sorts_before_identifiers(): void
    {
        $currency = new Currency('EUR');
        $smallIdLaterDate = $this->entryWithDebit(
            '2026-07-20',
            entryId: '30000000-0000-4000-8000-000000000001',
            currency: $currency,
        );
        $largeIdEarlierDate = $this->entryWithDebit(
            '2026-07-10',
            entryId: '30000000-0000-4000-8000-000000000002',
            currency: $currency,
        );

        $result = $this->generate([$smallIdLaterDate, $largeIdEarlierDate], currency: $currency);

        self::assertSame('2026-07-10', $result->lines()[0]->postingDate()->value()->format('Y-m-d'));
        self::assertSame('2026-07-20', $result->lines()[1]->postingDate()->value()->format('Y-m-d'));
    }

    public function test_input_entries_and_lines_are_not_mutated(): void
    {
        $entry = $this->entryWithDebit('2026-07-15');
        $status = $entry->status();
        $lines = $entry->lines();
        $line = $lines[0];
        $debit = $line->debit();

        $this->generate([$entry]);

        self::assertSame($status, $entry->status());
        self::assertSame($lines, $entry->lines());
        self::assertSame($line, $entry->lines()[0]);
        self::assertSame($debit, $entry->lines()[0]->debit());
    }

    public function test_all_output_amounts_remain_decimal_strings_without_floats(): void
    {
        $currency = new Currency('EUR');
        $entry = $this->entry('2026-07-15', JournalEntryStatus::Posted, self::ADMINISTRATION_ID, [
            $this->debitLine(self::ACCOUNT_ID, '0.12345678', $currency),
            $this->creditLine(self::ACCOUNT_ID, '0.02345678', $currency),
        ]);

        $result = $this->generate([$entry], currency: $currency);

        foreach ($result->lines() as $line) {
            self::assertIsString($line->debit()->amount());
            self::assertIsString($line->credit()->amount());
            self::assertIsString($line->runningBalance()->amount());
        }
        self::assertIsString($result->totalDebit()->amount());
        self::assertIsString($result->totalCredit()->amount());
        self::assertIsString($result->closingMovementBalance()->amount());
        self::assertSame('0.1', $result->closingMovementBalance()->amount());
    }

    /** @param list<JournalEntry> $entries */
    private function generate(
        array $entries,
        ?AdministrationId $administrationId = null,
        ?Currency $currency = null,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?LedgerAccountId $ledgerAccountId = null,
    ): GeneralLedgerResult {
        return (new GeneralLedgerReport)->generate(
            $entries,
            $administrationId ?? $this->administrationId(self::ADMINISTRATION_ID),
            $currency ?? new Currency('EUR'),
            $startDate ?? new DateTimeImmutable('2026-07-01'),
            $endDate ?? new DateTimeImmutable('2026-07-31'),
            $ledgerAccountId,
        );
    }

    private function entryWithDebit(
        string $date,
        JournalEntryStatus $status = JournalEntryStatus::Posted,
        string $administrationId = self::ADMINISTRATION_ID,
        ?string $entryId = null,
        ?Currency $currency = null,
    ): JournalEntry {
        $currency ??= new Currency('EUR');

        return $this->entry($date, $status, $administrationId, [
            $this->debitLine(self::ACCOUNT_ID, '10', $currency),
        ], $entryId);
    }

    /** @param list<JournalEntryLine> $lines */
    private function entry(
        string $date,
        JournalEntryStatus $status,
        string $administrationId,
        array $lines,
        ?string $entryId = null,
    ): JournalEntry {
        $entry = new JournalEntry(
            new JournalEntryId($entryId === null ? $this->nextUuid('3') : new Uuid($entryId)),
            $this->administrationId($administrationId),
            new JournalId($this->nextUuid('5')),
            new PostingDate(new DateTimeImmutable($date)),
            new JournalEntryReference('General ledger source'),
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

    private function debitLine(
        string $accountId,
        string $amount,
        Currency $currency,
        ?string $lineId = null,
    ): JournalEntryLine {
        return $this->line($accountId, new Money($amount, $currency), null, $lineId);
    }

    private function creditLine(
        string $accountId,
        string $amount,
        Currency $currency,
        ?string $lineId = null,
    ): JournalEntryLine {
        return $this->line($accountId, null, new Money($amount, $currency), $lineId);
    }

    private function line(
        string $accountId,
        ?Money $debit,
        ?Money $credit,
        ?string $lineId,
    ): JournalEntryLine {
        return new JournalEntryLine(
            new JournalEntryLineId($lineId === null ? $this->nextUuid('4') : new Uuid($lineId)),
            $this->ledgerAccountId($accountId),
            $debit,
            $credit,
            'General ledger line',
        );
    }

    private function assertEmptyMovement(GeneralLedgerResult $result): void
    {
        self::assertSame([], $result->lines());
        self::assertTrue($result->totalDebit()->isZero());
        self::assertTrue($result->totalCredit()->isZero());
        self::assertTrue($result->closingMovementBalance()->isZero());
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function ledgerAccountId(string $id): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid($id));
    }

    private function nextUuid(string $prefix): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $this->identitySequence++));
    }
}
