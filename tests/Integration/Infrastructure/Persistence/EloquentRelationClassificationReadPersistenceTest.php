<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Relations\CustomerReadRepository;
use App\Application\Relations\CustomerStore;
use App\Application\Relations\RelationClassificationReader;
use App\Application\Relations\RelationReadRepository;
use App\Application\Relations\RelationStore;
use App\Application\Relations\SupplierReadRepository;
use App\Application\Relations\SupplierStore;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Entities\Supplier;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentCustomerRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationClassificationReader;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSupplierRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentRelationClassificationReadPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    private EloquentRelationRepository $relations;

    private EloquentCustomerRepository $customers;

    private EloquentSupplierRepository $suppliers;

    private EloquentRelationClassificationReader $classifications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->relations = new EloquentRelationRepository;
        $this->customers = new EloquentCustomerRepository;
        $this->suppliers = new EloquentSupplierRepository;
        $this->classifications = new EloquentRelationClassificationReader;
        $this->createAdministration(self::ADMINISTRATION_A, 'A');
        $this->createAdministration(self::ADMINISTRATION_B, 'B');
    }

    public function test_relation_roundtrips_mutable_state_with_immutable_identity_code_and_tenant_ownership(): void
    {
        $relation = $this->relation(1, 'rel-01', 'Original name', true);
        $this->relations->save($this->administration(self::ADMINISTRATION_A), $relation);
        $relation->rename(new DisplayName('Renamed relation'));
        $relation->deactivate();
        $this->relations->save($this->administration(self::ADMINISTRATION_A), $relation);

        $read = $this->relations->findByIdForAdministration(
            $this->administration(self::ADMINISTRATION_A),
            $relation->id(),
        );

        self::assertNotNull($read);
        self::assertTrue($relation->id()->equals($read->id()));
        self::assertSame('REL-01', $read->code()->toString());
        self::assertSame('Renamed relation', $read->displayName()->toString());
        self::assertFalse($read->isActive());
        self::assertNull($this->relations->findByIdForAdministration($this->administration(self::ADMINISTRATION_B), $relation->id()));
        self::assertSame([], $this->relations->findForAdministration($this->administration(self::ADMINISTRATION_B)));
    }

    public function test_customer_and_supplier_roundtrip_independently_and_can_overlap(): void
    {
        $relation = $this->relation(1);
        $customer = $this->customer(1, $relation->id(), active: false);
        $supplier = $this->supplier(1, $relation->id());
        $this->relations->save($this->administration(self::ADMINISTRATION_A), $relation);
        $this->customers->save($this->administration(self::ADMINISTRATION_A), $customer);
        $this->suppliers->save($this->administration(self::ADMINISTRATION_A), $supplier);

        $readCustomer = $this->customers->findForAdministration($this->administration(self::ADMINISTRATION_A))[0];
        $readSupplier = $this->suppliers->findForAdministration($this->administration(self::ADMINISTRATION_A))[0];

        self::assertTrue($customer->id()->equals($readCustomer->id()));
        self::assertTrue($relation->id()->equals($readCustomer->relationId()));
        self::assertSame('CUST-01', $readCustomer->customerNumber()->toString());
        self::assertFalse($readCustomer->isActive());
        self::assertTrue($supplier->id()->equals($readSupplier->id()));
        self::assertTrue($relation->id()->equals($readSupplier->relationId()));
        self::assertSame('SUP-01', $readSupplier->supplierNumber()->toString());
        self::assertTrue($readSupplier->isActive());
        self::assertTrue($this->customers->existsForRelation($this->administration(self::ADMINISTRATION_A), $relation->id()));
        self::assertTrue($this->suppliers->existsForRelation($this->administration(self::ADMINISTRATION_A), $relation->id()));
        self::assertFalse($this->customers->existsForRelation($this->administration(self::ADMINISTRATION_B), $relation->id()));
        self::assertFalse($this->suppliers->existsForRelation($this->administration(self::ADMINISTRATION_B), $relation->id()));
        self::assertSame([], $this->customers->findForAdministration($this->administration(self::ADMINISTRATION_B)));
        self::assertSame([], $this->suppliers->findForAdministration($this->administration(self::ADMINISTRATION_B)));
    }

    public function test_classification_reader_returns_customer_supplier_both_and_neither_with_tenant_isolation(): void
    {
        foreach (range(1, 4) as $sequence) {
            $this->relations->save($this->administration(self::ADMINISTRATION_A), $this->relation($sequence));
        }

        $this->customers->save($this->administration(self::ADMINISTRATION_A), $this->customer(1, $this->relationId(1)));
        $this->suppliers->save($this->administration(self::ADMINISTRATION_A), $this->supplier(2, $this->relationId(2)));
        $this->customers->save($this->administration(self::ADMINISTRATION_A), $this->customer(3, $this->relationId(3)));
        $this->suppliers->save($this->administration(self::ADMINISTRATION_A), $this->supplier(3, $this->relationId(3)));

        $customer = $this->classifications->classify($this->administration(self::ADMINISTRATION_A), $this->relationId(1));
        $supplier = $this->classifications->classify($this->administration(self::ADMINISTRATION_A), $this->relationId(2));
        $both = $this->classifications->classify($this->administration(self::ADMINISTRATION_A), $this->relationId(3));
        $neither = $this->classifications->classify($this->administration(self::ADMINISTRATION_A), $this->relationId(4));
        $otherTenant = $this->classifications->classify($this->administration(self::ADMINISTRATION_B), $this->relationId(3));

        self::assertTrue($customer->isCustomer());
        self::assertFalse($customer->isSupplier());
        self::assertFalse($supplier->isCustomer());
        self::assertTrue($supplier->isSupplier());
        self::assertTrue($both->isCustomer());
        self::assertTrue($both->isSupplier());
        self::assertFalse($neither->isCustomer());
        self::assertFalse($neither->isSupplier());
        self::assertFalse($otherTenant->isCustomer());
        self::assertFalse($otherTenant->isSupplier());
    }

    public function test_database_rejects_cross_tenant_customer_and_supplier_links(): void
    {
        $relation = $this->relation(1);
        $this->relations->save($this->administration(self::ADMINISTRATION_A), $relation);

        foreach ([
            fn () => $this->customers->save($this->administration(self::ADMINISTRATION_B), $this->customer(1, $relation->id())),
            fn () => $this->suppliers->save($this->administration(self::ADMINISTRATION_B), $this->supplier(1, $relation->id())),
        ] as $write) {
            try {
                $write();
                self::fail('A classification cannot reference a Relation from another Administration.');
            } catch (QueryException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_duplicate_classification_and_identity_constraints_are_enforced(): void
    {
        $relation = $this->relation(1);
        $this->relations->save($this->administration(self::ADMINISTRATION_A), $relation);
        $this->customers->save($this->administration(self::ADMINISTRATION_A), $this->customer(1, $relation->id()));

        try {
            $this->customers->save($this->administration(self::ADMINISTRATION_A), $this->customer(2, $relation->id()));
            self::fail('A Relation can have only one Customer classification per Administration.');
        } catch (QueryException) {
            self::assertSame(1, CustomerRecord::query()->count());
        }

        $this->expectException(QueryException::class);
        CustomerRecord::query()->create(CustomerRecord::query()->firstOrFail()->getAttributes());
    }

    public function test_identity_cannot_move_between_tenants_and_relation_delete_is_restrictive(): void
    {
        $relation = $this->relation(1);
        $this->relations->save($this->administration(self::ADMINISTRATION_A), $relation);
        $this->customers->save($this->administration(self::ADMINISTRATION_A), $this->customer(1, $relation->id()));

        try {
            $this->relations->save($this->administration(self::ADMINISTRATION_B), $relation);
            self::fail('A Relation identity cannot move between Administrations.');
        } catch (DomainException) {
            self::assertSame(self::ADMINISTRATION_A, RelationRecord::query()->findOrFail($relation->id()->toString())->getAttribute('administration_id'));
        }

        $this->expectException(QueryException::class);
        RelationRecord::query()->whereKey($relation->id()->toString())->delete();
    }

    public function test_open_item_type_is_independent_from_relation_classification_overlap(): void
    {
        $relation = $this->relation(1);
        $this->relations->save($this->administration(self::ADMINISTRATION_A), $relation);
        $this->customers->save($this->administration(self::ADMINISTRATION_A), $this->customer(1, $relation->id()));
        $this->suppliers->save($this->administration(self::ADMINISTRATION_A), $this->supplier(1, $relation->id()));

        $receivable = $this->openItem($relation->id(), OpenItemType::Receivable, 1);
        $payable = $this->openItem($relation->id(), OpenItemType::Payable, 2);
        $classification = $this->classifications->classify($this->administration(self::ADMINISTRATION_A), $relation->id());

        self::assertTrue($classification->isCustomer());
        self::assertTrue($classification->isSupplier());
        self::assertSame(OpenItemType::Receivable, $receivable->type());
        self::assertSame(OpenItemType::Payable, $payable->type());
    }

    public function test_contracts_are_bound_to_narrow_adapters(): void
    {
        self::assertInstanceOf(EloquentRelationRepository::class, $this->app->make(RelationReadRepository::class));
        self::assertInstanceOf(EloquentRelationRepository::class, $this->app->make(RelationStore::class));
        self::assertInstanceOf(EloquentCustomerRepository::class, $this->app->make(CustomerReadRepository::class));
        self::assertInstanceOf(EloquentCustomerRepository::class, $this->app->make(CustomerStore::class));
        self::assertInstanceOf(EloquentSupplierRepository::class, $this->app->make(SupplierReadRepository::class));
        self::assertInstanceOf(EloquentSupplierRepository::class, $this->app->make(SupplierStore::class));
        self::assertInstanceOf(EloquentRelationClassificationReader::class, $this->app->make(RelationClassificationReader::class));
    }

    private function relation(int $sequence, ?string $code = null, string $name = 'Relation name', bool $active = true): Relation
    {
        return new Relation($this->relationId($sequence), new RelationCode($code ?? sprintf('REL-%02d', $sequence)), new DisplayName($name), $active);
    }

    private function customer(int $sequence, RelationId $relationId, bool $active = true): Customer
    {
        return new Customer(new CustomerId($this->uuid('3', $sequence)), $relationId, new CustomerNumber(sprintf('CUST-%02d', $sequence)), $active);
    }

    private function supplier(int $sequence, RelationId $relationId, bool $active = true): Supplier
    {
        return new Supplier(new SupplierId($this->uuid('4', $sequence)), $relationId, new SupplierNumber(sprintf('SUP-%02d', $sequence)), $active);
    }

    private function openItem(RelationId $relationId, OpenItemType $type, int $sequence): OpenItem
    {
        return new OpenItem(
            new OpenItemId($this->uuid('5', $sequence)),
            $this->administration(self::ADMINISTRATION_A),
            $relationId,
            new JournalEntryId($this->uuid('6', $sequence)),
            $type,
            new Money('100', new Currency('EUR')),
            new PostingDate(new DateTimeImmutable('2026-01-01')),
        );
    }

    private function relationId(int $sequence): RelationId
    {
        return new RelationId($this->uuid('2', $sequence));
    }

    private function uuid(string $prefix, int $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $sequence));
    }

    private function administration(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
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
}
