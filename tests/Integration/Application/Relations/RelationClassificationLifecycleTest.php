<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Relations;

use App\Application\Relations\ActivateCustomerClassification;
use App\Application\Relations\ActivateSupplierClassification;
use App\Application\Relations\ClassificationPersistenceConflict;
use App\Application\Relations\CustomerClassificationWriter;
use App\Application\Relations\DeactivateCustomerClassification;
use App\Application\Relations\DeactivateSupplierClassification;
use App\Application\Relations\RelationClassificationMutationResult;
use App\Application\Relations\RelationNumberSequenceProvisioner;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RelationClassificationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMINISTRATION_A, 'CLASS_A'));
        $administrations->save($this->administration(self::ADMINISTRATION_B, 'CLASS_B'));
        $this->relation(self::ADMINISTRATION_A, 1, 'A-001');
        $this->relation(self::ADMINISTRATION_A, 2, 'A-002');
        $this->relation(self::ADMINISTRATION_B, 3, 'B-001');
        $this->provision(self::ADMINISTRATION_A);
        $this->provision(self::ADMINISTRATION_B);
    }

    public function test_customer_create_idempotent_deactivate_and_reactivate_preserve_identity_number_and_counter(): void
    {
        $activate = $this->app->make(ActivateCustomerClassification::class);
        $deactivate = $this->app->make(DeactivateCustomerClassification::class);
        self::assertSame(RelationClassificationMutationResult::Success, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        $created = DB::table('customers')->where('relation_id', $this->relationId(1)->toString())->first();
        self::assertNotNull($created);
        self::assertSame('C000001', $created->customer_number);
        self::assertTrue((bool) $created->active);

        self::assertSame(RelationClassificationMutationResult::Success, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        $this->assertDatabaseCount('customers', 1);
        self::assertSame(RelationClassificationMutationResult::Success, $deactivate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        self::assertSame(RelationClassificationMutationResult::Success, $deactivate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        $this->assertDatabaseHas('customers', ['id' => $created->id, 'customer_number' => 'C000001', 'active' => false]);
        self::assertSame(RelationClassificationMutationResult::Success, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        $this->assertDatabaseHas('customers', ['id' => $created->id, 'customer_number' => 'C000001', 'active' => true]);
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'customer', 'next_value' => 2]);
    }

    public function test_supplier_lifecycle_is_independent_and_preserves_identity_number(): void
    {
        $activate = $this->app->make(ActivateSupplierClassification::class);
        $deactivate = $this->app->make(DeactivateSupplierClassification::class);
        self::assertSame(RelationClassificationMutationResult::Success, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        $created = DB::table('suppliers')->where('relation_id', $this->relationId(1)->toString())->first();
        self::assertNotNull($created);
        self::assertSame('S000001', $created->supplier_number);
        self::assertSame(RelationClassificationMutationResult::Success, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        self::assertSame(RelationClassificationMutationResult::Success, $deactivate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        self::assertSame(RelationClassificationMutationResult::Success, $deactivate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        self::assertSame(RelationClassificationMutationResult::Success, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));

        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseHas('suppliers', ['id' => $created->id, 'supplier_number' => 'S000001', 'active' => true]);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'supplier', 'next_value' => 2]);
    }

    public function test_number_allocation_is_sequential_per_type_and_independent_per_tenant(): void
    {
        $customer = $this->app->make(ActivateCustomerClassification::class);
        $supplier = $this->app->make(ActivateSupplierClassification::class);
        self::assertSame(RelationClassificationMutationResult::Success, $customer->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        self::assertSame(RelationClassificationMutationResult::Success, $customer->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(2)));
        self::assertSame(RelationClassificationMutationResult::Success, $supplier->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        self::assertSame(RelationClassificationMutationResult::Success, $customer->execute($this->administrationId(self::ADMINISTRATION_B), $this->relationId(3)));
        self::assertSame(RelationClassificationMutationResult::Success, $supplier->execute($this->administrationId(self::ADMINISTRATION_B), $this->relationId(3)));

        self::assertSame(['C000001', 'C000002'], DB::table('customers')->where('administration_id', self::ADMINISTRATION_A)->orderBy('customer_number')->pluck('customer_number')->all());
        self::assertSame(['S000001'], DB::table('suppliers')->where('administration_id', self::ADMINISTRATION_A)->pluck('supplier_number')->all());
        self::assertSame(['C000001'], DB::table('customers')->where('administration_id', self::ADMINISTRATION_B)->pluck('customer_number')->all());
        self::assertSame(['S000001'], DB::table('suppliers')->where('administration_id', self::ADMINISTRATION_B)->pluck('supplier_number')->all());
    }

    public function test_unknown_cross_tenant_and_missing_classification_are_typed_without_allocation(): void
    {
        $activate = $this->app->make(ActivateCustomerClassification::class);
        $deactivate = $this->app->make(DeactivateCustomerClassification::class);
        self::assertSame(RelationClassificationMutationResult::NotFound, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(99)));
        self::assertSame(RelationClassificationMutationResult::NotFound, $activate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(3)));
        self::assertSame(RelationClassificationMutationResult::NoClassification, $deactivate->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'customer', 'next_value' => 1]);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_sequence_missing_and_inactive_are_mapped_without_classification_write(): void
    {
        DB::table('relation_number_sequences')->where('administration_id', self::ADMINISTRATION_A)->where('sequence_type', 'customer')->delete();
        DB::table('relation_number_sequences')->where('administration_id', self::ADMINISTRATION_A)->where('sequence_type', 'supplier')->update(['active' => false]);

        self::assertSame(RelationClassificationMutationResult::SequenceMissing, $this->app->make(ActivateCustomerClassification::class)->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        self::assertSame(RelationClassificationMutationResult::SequenceInactive, $this->app->make(ActivateSupplierClassification::class)->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1)));
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_real_allocator_increment_rolls_back_when_customer_persistence_fails(): void
    {
        $this->app->instance(CustomerClassificationWriter::class, new class implements CustomerClassificationWriter
        {
            public function persist(AdministrationId $administrationId, Customer $customer): void
            {
                throw new ClassificationPersistenceConflict('Simulated write conflict.');
            }
        });

        $result = $this->app->make(ActivateCustomerClassification::class)->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1));

        self::assertSame(RelationClassificationMutationResult::PersistenceConflict, $result);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'customer', 'next_value' => 1]);
    }

    private function provision(string $administrationId): void
    {
        $this->app->make(RelationNumberSequenceProvisioner::class)->ensureForAdministration($this->administrationId($administrationId));
    }

    private function relation(string $administrationId, int $sequence, string $code): void
    {
        (new EloquentRelationRepository)->save(
            $this->administrationId($administrationId),
            new Relation($this->relationId($sequence), new RelationCode($code), new DisplayName('Relation '.$sequence), true),
        );
    }

    private function relationId(int $sequence): RelationId
    {
        return new RelationId(new Uuid(sprintf('60000000-0000-4000-8000-%012d', $sequence)));
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration($this->administrationId($id), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active);
    }
}
