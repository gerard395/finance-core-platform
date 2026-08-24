<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Application\Sales\SalesNumberAllocationStatus;
use App\Application\Sales\SalesNumberAllocator;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\SalesNumberType;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Sales\DatabaseSalesNumberSequence;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class SalesNumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN_A = '30000000-0000-4000-8000-000000000001';

    private const string ADMIN_B = '40000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMIN_A, 'SNUM_A'));
        $administrations->save($this->administration(self::ADMIN_B, 'SNUM_B'));
    }

    public function test_provisioning_creates_four_sequences_idempotently_without_resetting_state(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));
        DB::table('sales_number_sequences')->where('administration_id', self::ADMIN_A)->where('sequence_type', 'quotation')->update(['next_value' => 42, 'active' => false]);

        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));

        $this->assertDatabaseCount('sales_number_sequences', 4);
        $this->assertDatabaseHas('sales_number_sequences', ['administration_id' => self::ADMIN_A, 'sequence_type' => 'quotation', 'next_value' => 42, 'active' => false]);
        foreach (['order', 'sales_invoice', 'sales_credit_invoice'] as $type) {
            $this->assertDatabaseHas('sales_number_sequences', ['administration_id' => self::ADMIN_A, 'sequence_type' => $type, 'next_value' => 1, 'active' => true]);
        }
    }

    public function test_all_types_return_the_correct_value_object_and_independent_exact_format(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));
        $expectations = [
            [SalesNumberType::Quotation, QuotationNumber::class, 'Q000001'],
            [SalesNumberType::Order, OrderNumber::class, 'O000001'],
            [SalesNumberType::SalesInvoice, SalesInvoiceNumber::class, 'F000001'],
            [SalesNumberType::SalesCreditInvoice, SalesCreditInvoiceNumber::class, 'C000001'],
        ];

        foreach ($expectations as [$type, $class, $formatted]) {
            $result = $this->allocator()->next($this->administrationId(self::ADMIN_A), $type);
            self::assertSame(SalesNumberAllocationStatus::Success, $result->status());
            self::assertSame($type, $result->type());
            self::assertInstanceOf($class, $result->number());
            self::assertSame($formatted, $result->number()?->value());
        }

        self::assertSame('Q000002', $this->allocator()->next($this->administrationId(self::ADMIN_A), SalesNumberType::Quotation)->number()?->value());
    }

    public function test_administrations_have_independent_durable_counters(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_B));
        self::assertSame('F000001', $this->allocator()->next($this->administrationId(self::ADMIN_A), SalesNumberType::SalesInvoice)->number()?->value());
        self::assertSame('F000001', $this->allocator()->next($this->administrationId(self::ADMIN_B), SalesNumberType::SalesInvoice)->number()?->value());

        $fresh = new DatabaseSalesNumberSequence($this->app->make(TransactionManager::class));
        self::assertSame('F000002', $fresh->next($this->administrationId(self::ADMIN_A), SalesNumberType::SalesInvoice)->number()?->value());
        self::assertSame('F000002', $fresh->next($this->administrationId(self::ADMIN_B), SalesNumberType::SalesInvoice)->number()?->value());
    }

    public function test_missing_and_inactive_are_typed_and_never_increment(): void
    {
        $missing = $this->allocator()->next($this->administrationId(self::ADMIN_A), SalesNumberType::Order);
        self::assertSame(SalesNumberAllocationStatus::SequenceMissing, $missing->status());
        self::assertSame(SalesNumberType::Order, $missing->type());
        self::assertNull($missing->number());

        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));
        DB::table('sales_number_sequences')->where('administration_id', self::ADMIN_A)->where('sequence_type', 'sales_credit_invoice')->update(['active' => false]);
        $inactive = $this->allocator()->next($this->administrationId(self::ADMIN_A), SalesNumberType::SalesCreditInvoice);
        self::assertSame(SalesNumberAllocationStatus::SequenceInactive, $inactive->status());
        self::assertNull($inactive->number());
        $this->assertDatabaseHas('sales_number_sequences', ['administration_id' => self::ADMIN_A, 'sequence_type' => 'sales_credit_invoice', 'next_value' => 1]);
    }

    public function test_database_rejects_duplicates_unknown_types_and_non_positive_state(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));
        foreach ([
            ['sequence_type' => 'quotation', 'next_value' => 1],
            ['sequence_type' => 'purchase_invoice', 'next_value' => 1],
            ['sequence_type' => 'order', 'next_value' => 0],
        ] as $invalid) {
            try {
                DB::table('sales_number_sequences')->insert([
                    'administration_id' => self::ADMIN_A,
                    'sequence_type' => $invalid['sequence_type'],
                    'next_value' => $invalid['next_value'],
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                self::fail('Invalid Sales sequence state must be rejected.');
            } catch (QueryException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_allocation_participates_in_outer_transaction_and_rolls_back_increment(): void
    {
        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));
        try {
            $this->app->make(TransactionManager::class)->run(function (): void {
                self::assertSame('O000001', $this->allocator()->next($this->administrationId(self::ADMIN_A), SalesNumberType::Order)->number()?->value());
                throw new RuntimeException('Simulated document persistence failure.');
            });
        } catch (RuntimeException) {
            // The future document create transaction owns allocation and persistence together.
        }

        self::assertSame('O000001', $this->allocator()->next($this->administrationId(self::ADMIN_A), SalesNumberType::Order)->number()?->value());
    }

    public function test_concurrent_allocations_are_unique_and_consecutive(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the allocation concurrency test.');
        }

        $this->provisioner()->ensureForAdministration($this->administrationId(self::ADMIN_A));
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'sales-number-'), tempnam(sys_get_temp_dir(), 'sales-number-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->app->make(SalesNumberAllocator::class)->next($this->administrationId(self::ADMIN_A), SalesNumberType::SalesInvoice);
                    file_put_contents($file, $result->number()?->value() ?? $result->status()->name);
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

        self::assertSame(['F000001', 'F000002'], $numbers);
        DB::beginTransaction();
        $this->assertDatabaseHas('sales_number_sequences', ['administration_id' => self::ADMIN_A, 'sequence_type' => 'sales_invoice', 'next_value' => 3]);
    }

    public function test_contracts_are_bound_to_database_adapter(): void
    {
        self::assertInstanceOf(DatabaseSalesNumberSequence::class, $this->allocator());
        self::assertInstanceOf(DatabaseSalesNumberSequence::class, $this->provisioner());
    }

    private function allocator(): SalesNumberAllocator
    {
        return $this->app->make(SalesNumberAllocator::class);
    }

    private function provisioner(): SalesNumberSequenceProvisioner
    {
        return $this->app->make(SalesNumberSequenceProvisioner::class);
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
