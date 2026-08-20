<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Accounting\OpenItemStore;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemSettlementRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentOpenItemRepository implements OpenItemReadRepository, OpenItemSettlementStore, OpenItemStore
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
                'original_amount' => $openItem->originalAmount()->amount(),
                'currency' => $openItem->originalAmount()->currency()->code(),
                'opened_on' => $openItem->openedOn()->value()->format('Y-m-d'),
            ]);
        });
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

        return OpenItem::reconstitute(
            new OpenItemId(new Uuid($record->getAttribute('id'))),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new RelationId(new Uuid($record->getAttribute('relation_id'))),
            new JournalEntryId(new Uuid($record->getAttribute('journal_entry_id'))),
            OpenItemType::from($record->getAttribute('open_item_type')),
            new Money((string) $record->getAttribute('original_amount'), new Currency($record->getAttribute('currency'))),
            new PostingDate(new DateTimeImmutable($record->getAttribute('opened_on')->format('Y-m-d'))),
            $settlements,
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
