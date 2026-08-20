<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\RelationReadRepository;
use App\Application\Relations\RelationStore;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DomainException;

final class EloquentRelationRepository implements RelationReadRepository, RelationStore
{
    public function findByIdForAdministration(AdministrationId $administrationId, RelationId $relationId): ?Relation
    {
        $record = RelationRecord::query()
            ->whereKey($relationId->toString())
            ->where('administration_id', $administrationId->toString())
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findForAdministration(AdministrationId $administrationId): array
    {
        return RelationRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->orderBy('code')
            ->orderBy('id')
            ->get()
            ->map(fn (RelationRecord $record): Relation => $this->hydrate($record))
            ->all();
    }

    public function save(AdministrationId $administrationId, Relation $relation): void
    {
        if ($relation->contacts() !== [] || $relation->addresses() !== [] || $relation->bankAccounts() !== []) {
            throw new DomainException('Relation child persistence is outside this repository contract.');
        }

        $existing = RelationRecord::query()->find($relation->id()->toString());

        if ($existing !== null && $existing->getAttribute('administration_id') !== $administrationId->toString()) {
            throw new DomainException('A Relation identity belongs to another Administration.');
        }

        if ($existing !== null && $existing->getAttribute('code') !== $relation->code()->toString()) {
            throw new DomainException('A Relation code is immutable.');
        }

        RelationRecord::query()->updateOrCreate(
            ['id' => $relation->id()->toString()],
            [
                'administration_id' => $administrationId->toString(),
                'code' => $relation->code()->toString(),
                'display_name' => $relation->displayName()->toString(),
                'active' => $relation->isActive(),
            ],
        );
    }

    private function hydrate(RelationRecord $record): Relation
    {
        return new Relation(
            new RelationId(new Uuid($record->getAttribute('id'))),
            new RelationCode($record->getAttribute('code')),
            new DisplayName($record->getAttribute('display_name')),
            (bool) $record->getAttribute('active'),
        );
    }
}
