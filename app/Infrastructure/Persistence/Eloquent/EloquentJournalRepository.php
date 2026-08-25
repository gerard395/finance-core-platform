<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\JournalStore;
use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\ValueObjects\JournalCode;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\JournalName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentJournalRepository implements JournalReadRepository, JournalStore
{
    public function findByIdForAdministration(AdministrationId $administrationId, JournalId $journalId): ?Journal
    {
        $record = JournalRecord::query()->where('administration_id', $administrationId->toString())->whereKey($journalId->toString())->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findForAdministration(AdministrationId $administrationId): array
    {
        return JournalRecord::query()->where('administration_id', $administrationId->toString())->orderBy('code')->orderBy('id')->get()->map(fn (JournalRecord $record): Journal => $this->hydrate($record))->all();
    }

    public function save(AdministrationId $administrationId, Journal $journal): void
    {
        $existing = JournalRecord::query()->find($journal->id()->toString());
        if ($existing !== null && $existing->getAttribute('administration_id') !== $administrationId->toString()) {
            throw new DomainException('A Journal identity belongs to another Administration.');
        }

        try {
            JournalRecord::query()->updateOrCreate(
                ['id' => $journal->id()->toString()],
                ['administration_id' => $administrationId->toString(), 'code' => $journal->code()->value(), 'name' => $journal->name()->value(), 'type' => $journal->type()->value, 'status' => $journal->status()->value],
            );
        } catch (QueryException $exception) {
            throw new DomainException('Journal master data could not be persisted.', previous: $exception);
        }
    }

    private function hydrate(JournalRecord $record): Journal
    {
        return new Journal(new JournalId(new Uuid($record->getAttribute('id'))), new JournalCode($record->getAttribute('code')), new JournalName($record->getAttribute('name')), JournalType::from($record->getAttribute('type')), JournalStatus::from($record->getAttribute('status')));
    }
}
