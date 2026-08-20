<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Application\Relations\RelationNumberAllocationStatus;
use App\Application\Relations\RelationNumberAllocator;
use App\Application\Relations\RelationNumberSequenceProvisioner;
use App\Application\Relations\RelationNumberType;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Relations\DatabaseRelationNumberSequence;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class RelationNumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMINISTRATION_A, 'NUM_A'));
        $administrations->save($this->administration(self::ADMINISTRATION_B, 'NUM_B'));
    }

    public function test_provisioning_creates_both_series_idempotently_without_resetting_state(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));
        DB::table('relation_number_sequences')->where('administration_id', self::ADMINISTRATION_A)->where('sequence_type', 'customer')->update(['next_value' => 42]);

        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));

        $this->assertDatabaseCount('relation_number_sequences', 2);
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'customer', 'next_value' => 42, 'active' => true]);
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'supplier', 'next_value' => 1, 'active' => true]);
    }

    public function test_customer_and_supplier_formats_and_counters_are_independent(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));

        $customerOne = $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer);
        $supplierOne = $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Supplier);
        $customerTwo = $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer);

        self::assertSame(RelationNumberAllocationStatus::Success, $customerOne->status());
        self::assertInstanceOf(CustomerNumber::class, $customerOne->number());
        self::assertSame('C000001', $customerOne->number()?->toString());
        self::assertInstanceOf(SupplierNumber::class, $supplierOne->number());
        self::assertSame('S000001', $supplierOne->number()?->toString());
        self::assertSame('C000002', $customerTwo->number()?->toString());
    }

    public function test_administrations_have_independent_durable_counters(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_B));
        self::assertSame('C000001', $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer)->number()?->toString());
        self::assertSame('C000001', $this->allocator()->next($this->administrationId(self::ADMINISTRATION_B), RelationNumberType::Customer)->number()?->toString());

        $freshAdapter = new DatabaseRelationNumberSequence($this->app->make(TransactionManager::class));

        self::assertSame('C000002', $freshAdapter->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer)->number()?->toString());
        self::assertSame('C000002', $freshAdapter->next($this->administrationId(self::ADMINISTRATION_B), RelationNumberType::Customer)->number()?->toString());
    }

    public function test_missing_and_inactive_sequences_return_typed_failures_without_increment(): void
    {
        $missing = $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer);
        self::assertSame(RelationNumberAllocationStatus::SequenceMissing, $missing->status());
        self::assertNull($missing->number());
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));
        DB::table('relation_number_sequences')->where('administration_id', self::ADMINISTRATION_A)->where('sequence_type', 'supplier')->update(['active' => false]);

        $inactive = $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Supplier);

        self::assertSame(RelationNumberAllocationStatus::SequenceInactive, $inactive->status());
        self::assertNull($inactive->number());
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'supplier', 'next_value' => 1]);
    }

    public function test_duplicate_sequence_and_non_positive_state_are_rejected_by_database_constraints(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));
        try {
            DB::table('relation_number_sequences')->insert([
                'administration_id' => self::ADMINISTRATION_A,
                'sequence_type' => 'customer',
                'next_value' => 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            self::fail('Duplicate Relation number sequence must be rejected.');
        } catch (QueryException) {
            self::assertTrue(true);
        }

        $this->expectException(QueryException::class);
        DB::table('relation_number_sequences')->where('administration_id', self::ADMINISTRATION_A)->where('sequence_type', 'customer')->update(['next_value' => 0]);
    }

    public function test_allocation_participates_in_an_outer_business_transaction(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));
        try {
            $this->app->make(TransactionManager::class)->run(function (): void {
                $result = $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer);
                self::assertSame('C000001', $result->number()?->toString());
                throw new RuntimeException('Simulated classification persistence failure.');
            });
        } catch (RuntimeException) {
            // The outer business transaction owns both allocation and later classification persistence.
        }

        self::assertSame('C000001', $this->allocator()->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer)->number()?->toString());
    }

    public function test_concurrent_allocations_are_serialized_to_unique_consecutive_numbers(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the allocation concurrency test.');
        }

        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMINISTRATION_A));
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'relation-number-'), tempnam(sys_get_temp_dir(), 'relation-number-')];
        $children = [];

        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->app->make(RelationNumberAllocator::class)->next($this->administrationId(self::ADMINISTRATION_A), RelationNumberType::Customer);
                    file_put_contents($file, $result->number()?->toString() ?? $result->status()->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        $numbers = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($numbers);

        self::assertSame(['C000001', 'C000002'], $numbers);
        DB::beginTransaction();
        $this->assertDatabaseHas('relation_number_sequences', ['administration_id' => self::ADMINISTRATION_A, 'sequence_type' => 'customer', 'next_value' => 3]);
    }

    public function test_contracts_are_bound_to_the_database_adapter(): void
    {
        self::assertInstanceOf(DatabaseRelationNumberSequence::class, $this->allocator());
        self::assertInstanceOf(DatabaseRelationNumberSequence::class, $this->provisioner());
    }

    private function allocator(): RelationNumberAllocator
    {
        return $this->app->make(RelationNumberAllocator::class);
    }

    private function provisioner(): RelationNumberSequenceProvisioner
    {
        return $this->app->make(RelationNumberSequenceProvisioner::class);
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration(
            $this->administrationId($id),
            new AdministrationCode($code),
            new AdministrationName($code),
            null,
            new Currency('EUR'),
            AdministrationStatus::Active,
        );
    }
}
