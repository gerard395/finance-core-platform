<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\ClassificationPersistenceConflict;
use App\Application\Relations\SupplierClassificationWriter;
use App\Application\Relations\SupplierReadRepository;
use App\Application\Relations\SupplierStore;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Supplier;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\SupplierRecord;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentSupplierRepository implements SupplierClassificationWriter, SupplierReadRepository, SupplierStore
{
    public function findForAdministration(AdministrationId $administrationId): array
    {
        return SupplierRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->orderBy('supplier_number')
            ->orderBy('id')
            ->get()
            ->map(static fn (SupplierRecord $record): Supplier => new Supplier(
                new SupplierId(new Uuid($record->getAttribute('id'))),
                new RelationId(new Uuid($record->getAttribute('relation_id'))),
                new SupplierNumber($record->getAttribute('supplier_number')),
                (bool) $record->getAttribute('active'),
            ))
            ->all();
    }

    public function existsForRelation(AdministrationId $administrationId, RelationId $relationId): bool
    {
        return SupplierRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->exists();
    }

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId): ?Supplier
    {
        $record = SupplierRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function persist(AdministrationId $administrationId, Supplier $supplier): void
    {
        try {
            $this->save($administrationId, $supplier);
        } catch (DomainException|QueryException $exception) {
            throw new ClassificationPersistenceConflict('Supplier classification could not be persisted.', previous: $exception);
        }
    }

    public function save(AdministrationId $administrationId, Supplier $supplier): void
    {
        $existing = SupplierRecord::query()->find($supplier->id()->toString());

        if ($existing !== null && $existing->getAttribute('administration_id') !== $administrationId->toString()) {
            throw new DomainException('A Supplier identity belongs to another Administration.');
        }

        if ($existing !== null && ($existing->getAttribute('relation_id') !== $supplier->relationId()->toString()
            || $existing->getAttribute('supplier_number') !== $supplier->supplierNumber()->toString())) {
            throw new DomainException('Supplier Relation and number are immutable.');
        }

        SupplierRecord::query()->updateOrCreate(
            ['id' => $supplier->id()->toString()],
            [
                'administration_id' => $administrationId->toString(),
                'relation_id' => $supplier->relationId()->toString(),
                'supplier_number' => $supplier->supplierNumber()->toString(),
                'active' => $supplier->isActive(),
            ],
        );
    }

    private function hydrate(SupplierRecord $record): Supplier
    {
        return new Supplier(
            new SupplierId(new Uuid($record->getAttribute('id'))),
            new RelationId(new Uuid($record->getAttribute('relation_id'))),
            new SupplierNumber($record->getAttribute('supplier_number')),
            (bool) $record->getAttribute('active'),
        );
    }
}
