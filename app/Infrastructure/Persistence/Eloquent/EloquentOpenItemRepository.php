<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Accounting\OpenItemMatchAppendResult;
use App\Application\Accounting\OpenItemMatchPair;
use App\Application\Accounting\OpenItemMatchRepository;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Accounting\OpenItemStore;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemMatch;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemMatchId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemMatchRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemSettlementRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentOpenItemRepository implements OpenItemMatchRepository, OpenItemReadRepository, OpenItemSettlementStore, OpenItemStore
{
    public function findForAdministrationAsOf(
        AdministrationId $administrationId,
        PostingDate $asOfDate,
    ): array {
        return OpenItemRecord::query()
            ->with('settlements')
            ->where('administration_id', $administrationId->toString())
            ->whereDate('opened_on', '<=', $asOfDate->value()->format('Y-m-d'))
            ->orderBy('opened_on')
            ->orderBy('id')
            ->get()
            ->map(fn (OpenItemRecord $record): OpenItem => $this->hydrate($record))
            ->all();
    }

    public function append(OpenItem $openItem): void
    {
        if ($openItem->settlements() !== []) {
            throw new DomainException('A new OpenItem basis fact must be appended before its settlements.');
        }

        DB::transaction(function () use ($openItem): void {
            if (OpenItemRecord::query()->whereKey($openItem->id()->toString())->exists()) {
                throw new DomainException('An OpenItem with this identity already exists.');
            }

            $this->assertPostedJournalEntry(
                $openItem->administrationId(),
                $openItem->journalEntryId(),
            );

            OpenItemRecord::query()->create([
                'id' => $openItem->id()->toString(),
                'administration_id' => $openItem->administrationId()->toString(),
                'relation_id' => $openItem->relationId()->toString(),
                'journal_entry_id' => $openItem->journalEntryId()->toString(),
                'open_item_type' => $openItem->type()->value,
                'side' => $openItem->side()->value,
                'original_amount' => $openItem->originalAmount()->amount(),
                'currency' => $openItem->originalAmount()->currency()->code(),
                'opened_on' => $openItem->openedOn()->value()->format('Y-m-d'),
                'due_date' => $openItem->dueDate()?->format('Y-m-d'),
            ]);
        });
    }

    public function findLockedPair(AdministrationId $administrationId, OpenItemId $debitOpenItemId, OpenItemId $creditOpenItemId): ?OpenItemMatchPair
    {
        $ids = [$debitOpenItemId->toString(), $creditOpenItemId->toString()];
        sort($ids);
        $records = OpenItemRecord::query()
            ->with('settlements')
            ->where('administration_id', $administrationId->toString())
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $debit = $records->get($debitOpenItemId->toString());
        $credit = $records->get($creditOpenItemId->toString());

        return $debit instanceof OpenItemRecord && $credit instanceof OpenItemRecord
            ? new OpenItemMatchPair($this->hydrate($debit), $this->hydrate($credit))
            : null;
    }

    public function findLocked(AdministrationId $administrationId, OpenItemId $openItemId): ?OpenItem
    {
        $record = OpenItemRecord::query()
            ->with('settlements')
            ->where('administration_id', $administrationId->toString())
            ->whereKey($openItemId->toString())
            ->lockForUpdate()
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function appendMatch(OpenItemMatch $match): OpenItemMatchAppendResult
    {
        if (OpenItemMatchRecord::query()->whereKey($match->id()->toString())->exists()) {
            return OpenItemMatchAppendResult::AlreadyExists;
        }

        $this->assertPostedJournalEntry($match->administrationId(), $match->sourceJournalEntryId());

        try {
            OpenItemMatchRecord::query()->create([
                'id' => $match->id()->toString(),
                'administration_id' => $match->administrationId()->toString(),
                'debit_open_item_id' => $match->debitOpenItemId()->toString(),
                'credit_open_item_id' => $match->creditOpenItemId()->toString(),
                'amount' => $match->amount()->amount(),
                'currency' => $match->amount()->currency()->code(),
                'occurred_on' => $match->occurredOn()->value()->format('Y-m-d'),
                'source_journal_entry_id' => $match->sourceJournalEntryId()->toString(),
            ]);
        } catch (QueryException $exception) {
            if (OpenItemMatchRecord::query()->whereKey($match->id()->toString())->exists()) {
                return OpenItemMatchAppendResult::AlreadyExists;
            }
            throw $exception;
        }

        return OpenItemMatchAppendResult::Appended;
    }

    public function appendSettlement(OpenItem $openItem, OpenItemSettlement $settlement): void
    {
        if ($openItem->settlement($settlement->id()) !== $settlement) {
            throw new DomainException('Only a settlement owned and validated by the OpenItem can be appended.');
        }

        DB::transaction(function () use ($openItem, $settlement): void {
            $record = OpenItemRecord::query()->whereKey($openItem->id()->toString())->lockForUpdate()->first();

            if ($record === null || $record->getAttribute('administration_id') !== $openItem->administrationId()->toString()) {
                throw new DomainException('The persisted OpenItem does not belong to this Administration.');
            }

            $record->load('settlements');
            $current = $this->hydrate($record);
            if ($settlement->type() === OpenItemSettlementType::Applied) {
                $current->applySettlement($settlement->id(), $settlement->effectiveDate(), $settlement->amount(), $settlement->sourceJournalEntryId());
            } else {
                $reversedSettlementId = $settlement->reversedSettlementId();
                if ($reversedSettlementId === null) {
                    throw new DomainException('A reversal must reference an applied settlement.');
                }
                $current->reverseSettlement($settlement->id(), $settlement->effectiveDate(), $reversedSettlementId, $settlement->sourceJournalEntryId());
            }

            if (OpenItemSettlementRecord::query()->whereKey($settlement->id()->toString())->exists()) {
                throw new DomainException('An OpenItem settlement with this identity already exists.');
            }

            $persistedIds = OpenItemSettlementRecord::query()
                ->where('open_item_id', $openItem->id()->toString())
                ->pluck('id')->sort()->values()->all();
            $expectedIds = array_values(array_filter(
                array_map(static fn (OpenItemSettlement $item): string => $item->id()->toString(), $openItem->settlements()),
                static fn (string $id): bool => $id !== $settlement->id()->toString(),
            ));
            sort($expectedIds);

            if ($persistedIds !== $expectedIds) {
                throw new DomainException('Persisted settlement history must exactly precede the new settlement fact.');
            }

            $this->assertPostedJournalEntry(
                $openItem->administrationId(),
                $settlement->sourceJournalEntryId(),
            );

            OpenItemSettlementRecord::query()->create([
                'id' => $settlement->id()->toString(),
                'administration_id' => $openItem->administrationId()->toString(),
                'open_item_id' => $openItem->id()->toString(),
                'effective_date' => $settlement->effectiveDate()->value()->format('Y-m-d'),
                'amount' => $settlement->amount()->amount(),
                'currency' => $settlement->amount()->currency()->code(),
                'source_journal_entry_id' => $settlement->sourceJournalEntryId()->toString(),
                'type' => $settlement->type()->value,
                'reversed_settlement_id' => $settlement->reversedSettlementId()?->toString(),
            ]);
        });
    }

    private function hydrate(OpenItemRecord $record): OpenItem
    {
        /** @var Collection<int, OpenItemSettlementRecord> $settlementRecords */
        $settlementRecords = $record->getRelation('settlements');
        $settlements = $settlementRecords->map(static function (OpenItemSettlementRecord $settlement): OpenItemSettlement {
            $reversedId = $settlement->getAttribute('reversed_settlement_id');

            return new OpenItemSettlement(
                new OpenItemSettlementId(new Uuid($settlement->getAttribute('id'))),
                new PostingDate(new DateTimeImmutable($settlement->getAttribute('effective_date')->format('Y-m-d'))),
                new Money((string) $settlement->getAttribute('amount'), new Currency($settlement->getAttribute('currency'))),
                new JournalEntryId(new Uuid($settlement->getAttribute('source_journal_entry_id'))),
                OpenItemSettlementType::from($settlement->getAttribute('type')),
                $reversedId === null ? null : new OpenItemSettlementId(new Uuid($reversedId)),
            );
        })->all();
        $matches = OpenItemMatchRecord::query()
            ->where('administration_id', $record->getAttribute('administration_id'))
            ->where(static fn ($query) => $query
                ->where('debit_open_item_id', $record->getAttribute('id'))
                ->orWhere('credit_open_item_id', $record->getAttribute('id')))
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get()
            ->map(static fn (OpenItemMatchRecord $match): OpenItemMatch => new OpenItemMatch(
                new OpenItemMatchId(new Uuid($match->getAttribute('id'))),
                new AdministrationId(new Uuid($match->getAttribute('administration_id'))),
                new OpenItemId(new Uuid($match->getAttribute('debit_open_item_id'))),
                new OpenItemId(new Uuid($match->getAttribute('credit_open_item_id'))),
                new Money((string) $match->getAttribute('amount'), new Currency($match->getAttribute('currency'))),
                new PostingDate(new DateTimeImmutable($match->getAttribute('occurred_on')->format('Y-m-d'))),
                new JournalEntryId(new Uuid($match->getAttribute('source_journal_entry_id'))),
            ))->all();

        return OpenItem::reconstitute(
            new OpenItemId(new Uuid($record->getAttribute('id'))),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new RelationId(new Uuid($record->getAttribute('relation_id'))),
            new JournalEntryId(new Uuid($record->getAttribute('journal_entry_id'))),
            OpenItemType::from($record->getAttribute('open_item_type')),
            new Money((string) $record->getAttribute('original_amount'), new Currency($record->getAttribute('currency'))),
            new PostingDate(new DateTimeImmutable($record->getAttribute('opened_on')->format('Y-m-d'))),
            $settlements,
            OpenItemSide::from($record->getAttribute('side')),
            $matches,
            ($dueDate = $record->getAttribute('due_date')) === null ? null : new DateTimeImmutable($dueDate->format('Y-m-d')),
        );
    }

    private function assertPostedJournalEntry(AdministrationId $administrationId, JournalEntryId $journalEntryId): void
    {
        $exists = JournalEntryRecord::query()
            ->whereKey($journalEntryId->toString())
            ->where('administration_id', $administrationId->toString())
            ->where('status', JournalEntryStatus::Posted->value)
            ->exists();

        if (! $exists) {
            throw new DomainException('An OpenItem financial fact must reference a Posted JournalEntry in the same Administration.');
        }
    }
}
