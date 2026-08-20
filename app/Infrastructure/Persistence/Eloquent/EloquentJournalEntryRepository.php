<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Accounting\JournalEntryReadRepository;
use App\Application\Accounting\JournalEntryStore;
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
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentJournalEntryRepository implements JournalEntryReadRepository, JournalEntryStore
{
    public function findPostedForAdministrationAndPeriod(
        AdministrationId $administrationId,
        PostingDate $startDate,
        PostingDate $endDate,
    ): array {
        if ($startDate->value() > $endDate->value()) {
            throw new DomainException('Journal entry read start date cannot be after end date.');
        }

        return JournalEntryRecord::query()
            ->with('lines')
            ->where('administration_id', $administrationId->toString())
            ->where('status', JournalEntryStatus::Posted->value)
            ->whereBetween('posting_date', [
                $startDate->value()->format('Y-m-d'),
                $endDate->value()->format('Y-m-d'),
            ])
            ->orderBy('posting_date')
            ->orderBy('id')
            ->get()
            ->map(fn (JournalEntryRecord $record): JournalEntry => $this->hydrate($record))
            ->all();
    }

    public function append(JournalEntry $journalEntry): void
    {
        if (! $journalEntry->isPosted()) {
            throw new DomainException('Only Posted journal entries can be appended as financial facts.');
        }

        DB::transaction(function () use ($journalEntry): void {
            if (JournalEntryRecord::query()->whereKey($journalEntry->id()->toString())->exists()) {
                throw new DomainException('A journal entry with this identity already exists.');
            }

            $accountIds = array_values(array_unique(array_map(
                static fn (JournalEntryLine $line): string => $line->ledgerAccountId()->toString(),
                $journalEntry->lines(),
            )));
            $ownedAccountCount = LedgerAccountRecord::query()
                ->where('administration_id', $journalEntry->administrationId()->toString())
                ->whereIn('id', $accountIds)
                ->count();

            if ($ownedAccountCount !== count($accountIds)) {
                throw new DomainException('Every journal entry line must reference a LedgerAccount in the same Administration.');
            }

            JournalEntryRecord::query()->create([
                'id' => $journalEntry->id()->toString(),
                'administration_id' => $journalEntry->administrationId()->toString(),
                'journal_id' => $journalEntry->journalId()->toString(),
                'posting_date' => $journalEntry->postingDate()->value()->format('Y-m-d'),
                'reference' => $journalEntry->reference()->toString(),
                'status' => $journalEntry->status()->value,
            ]);

            foreach ($journalEntry->lines() as $line) {
                $money = $line->debit() ?? $line->credit();

                JournalEntryLineRecord::query()->create([
                    'id' => $line->id()->toString(),
                    'administration_id' => $journalEntry->administrationId()->toString(),
                    'journal_entry_id' => $journalEntry->id()->toString(),
                    'ledger_account_id' => $line->ledgerAccountId()->toString(),
                    'debit_amount' => $line->debit()?->amount(),
                    'credit_amount' => $line->credit()?->amount(),
                    'currency' => $money?->currency()->code(),
                    'description' => $line->description(),
                ]);
            }
        });
    }

    private function hydrate(JournalEntryRecord $record): JournalEntry
    {
        /** @var Collection<int, JournalEntryLineRecord> $lineRecords */
        $lineRecords = $record->getRelation('lines');
        $lines = $lineRecords->map(static function (JournalEntryLineRecord $line): JournalEntryLine {
            $currency = new Currency($line->getAttribute('currency'));
            $debit = $line->getAttribute('debit_amount');
            $credit = $line->getAttribute('credit_amount');

            return new JournalEntryLine(
                new JournalEntryLineId(new Uuid($line->getAttribute('id'))),
                new LedgerAccountId(new Uuid($line->getAttribute('ledger_account_id'))),
                $debit === null ? null : new Money((string) $debit, $currency),
                $credit === null ? null : new Money((string) $credit, $currency),
                $line->getAttribute('description'),
            );
        })->all();

        return JournalEntry::reconstitute(
            new JournalEntryId(new Uuid($record->getAttribute('id'))),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new JournalId(new Uuid($record->getAttribute('journal_id'))),
            new PostingDate(new DateTimeImmutable($record->getAttribute('posting_date')->format('Y-m-d'))),
            new JournalEntryReference($record->getAttribute('reference')),
            JournalEntryStatus::from($record->getAttribute('status')),
            $lines,
        );
    }
}
