<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\ClassificationPersistenceConflict;
use App\Application\Relations\CustomerClassificationWriter;
use App\Application\Relations\CustomerReadRepository;
use App\Application\Relations\CustomerStore;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentCustomerRepository implements CustomerClassificationWriter, CustomerReadRepository, CustomerStore
{
    public function findForAdministration(AdministrationId $administrationId): array
    {
        return CustomerRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->orderBy('customer_number')
            ->orderBy('id')
            ->get()
            ->map(static fn (CustomerRecord $record): Customer => new Customer(
                new CustomerId(new Uuid($record->getAttribute('id'))),
                new RelationId(new Uuid($record->getAttribute('relation_id'))),
                new CustomerNumber($record->getAttribute('customer_number')),
                (bool) $record->getAttribute('active'),
            ))
            ->all();
    }

    public function existsForRelation(AdministrationId $administrationId, RelationId $relationId): bool
    {
        return CustomerRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->exists();
    }

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId): ?Customer
    {
        $record = CustomerRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function persist(AdministrationId $administrationId, Customer $customer): void
    {
        try {
            $this->save($administrationId, $customer);
        } catch (DomainException|QueryException $exception) {
            throw new ClassificationPersistenceConflict('Customer classification could not be persisted.', previous: $exception);
        }
    }

    public function save(AdministrationId $administrationId, Customer $customer): void
    {
        $existing = CustomerRecord::query()->find($customer->id()->toString());

        if ($existing !== null && $existing->getAttribute('administration_id') !== $administrationId->toString()) {
            throw new DomainException('A Customer identity belongs to another Administration.');
        }

        if ($existing !== null && ($existing->getAttribute('relation_id') !== $customer->relationId()->toString()
            || $existing->getAttribute('customer_number') !== $customer->customerNumber()->toString())) {
            throw new DomainException('Customer Relation and number are immutable.');
        }

        CustomerRecord::query()->updateOrCreate(
            ['id' => $customer->id()->toString()],
            [
                'administration_id' => $administrationId->toString(),
                'relation_id' => $customer->relationId()->toString(),
                'customer_number' => $customer->customerNumber()->toString(),
                'active' => $customer->isActive(),
            ],
        );
    }

    private function hydrate(CustomerRecord $record): Customer
    {
        return new Customer(
            new CustomerId(new Uuid($record->getAttribute('id'))),
            new RelationId(new Uuid($record->getAttribute('relation_id'))),
            new CustomerNumber($record->getAttribute('customer_number')),
            (bool) $record->getAttribute('active'),
        );
    }
}
