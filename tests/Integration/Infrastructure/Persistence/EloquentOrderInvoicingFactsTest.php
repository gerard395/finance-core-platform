<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Sales\OrderInvoiceDraftRequestReader;
use App\Application\Sales\OrderInvoicingFactAppendResult;
use App\Application\Sales\OrderInvoicingFactStore;
use App\Application\Sales\OrderInvoicingProgressReader;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\OrderInvoiceAllocation;
use App\Domain\Sales\Entities\OrderInvoiceDraftRequest;
use App\Domain\Sales\Entities\OrderInvoiceReservation;
use App\Domain\Sales\Entities\OrderInvoiceReservationRelease;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceAllocationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationReleaseId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class EloquentOrderInvoicingFactsTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'a7000000-0000-4000-8000-000000000001';

    private const B = 'b7000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTenant(self::A, 'A');
        $this->seedTenant(self::B, 'B');
        foreach ([1, 2, 3] as $invoice) {
            $this->seedInvoice(self::A, $invoice, true);
        }
        $this->seedInvoice(self::B, 1, true);
        $this->seedInvoice(self::A, 9, false);
    }

    public function test_append_only_facts_roundtrip_release_consumption_and_ledger_are_exact(): void
    {
        $store = $this->app->make(OrderInvoicingFactStore::class);
        $reader = $this->app->make(OrderInvoicingProgressReader::class);
        $request = $this->request(self::A, 1);
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendDraftRequest($request));
        self::assertSame(OrderInvoicingFactAppendResult::AlreadyExists, $store->appendDraftRequest($request));
        $storedRequest = $this->app->make(OrderInvoiceDraftRequestReader::class)->find($this->admin(self::A), $request->id());
        self::assertTrue($request->id()->equals($storedRequest?->id()));
        self::assertTrue($request->orderId()->equals($storedRequest?->orderId()));
        self::assertTrue($request->salesInvoiceId()->equals($storedRequest?->salesInvoiceId()));
        self::assertNull($this->app->make(OrderInvoiceDraftRequestReader::class)->find($this->admin(self::B), $request->id()));

        $reservation = $this->reservation(self::A, 1, '4');
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendReservation($reservation));
        self::assertSame(OrderInvoicingFactAppendResult::AlreadyExists, $store->appendReservation($reservation));
        $line = $reader->progress($this->admin(self::A), $this->order(self::A))?->lines()[0];
        self::assertSame('10', $line?->ordered()->value());
        self::assertSame('4', $line?->reserved()->value());
        self::assertSame('0', $line?->allocated()->value());
        self::assertSame('6', $line?->available()->value());
        self::assertCount(1, $reader->activeReservationsForOrder($this->admin(self::A), $this->order(self::A)));
        self::assertCount(1, $reader->reservationsForDraftRequest($this->admin(self::A), $request->id()));
        self::assertCount(1, $reader->reservationsForSalesInvoice($this->admin(self::A), $this->invoice(self::A, 1)));

        $release = new OrderInvoiceReservationRelease($this->releaseId(1), $this->admin(self::A), $reservation->id());
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendRelease($release));
        self::assertSame(OrderInvoicingFactAppendResult::AlreadyExists, $store->appendRelease($release));
        $line = $reader->progress($this->admin(self::A), $this->order(self::A))?->lines()[0];
        self::assertSame('0', $line?->reserved()->value());
        self::assertSame('10', $line?->available()->value());
        $this->assertDatabaseHas('order_invoice_reservations', ['id' => $reservation->id()->toString()]);
        $this->assertDatabaseHas('order_invoice_reservation_releases', ['id' => $release->id()->toString()]);
        self::assertSame(OrderInvoicingFactAppendResult::InvalidFactState, $store->appendAllocation($this->allocation(self::A, 1, '4')));

        $store->appendDraftRequest($this->request(self::A, 2));
        $second = $this->reservation(self::A, 2, '4');
        $store->appendReservation($second);
        $allocation = $this->allocation(self::A, 2, '4');
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendAllocation($allocation));
        self::assertSame(OrderInvoicingFactAppendResult::AlreadyExists, $store->appendAllocation($allocation));
        $line = $reader->progress($this->admin(self::A), $this->order(self::A))?->lines()[0];
        self::assertSame('0', $line?->reserved()->value());
        self::assertSame('4', $line?->allocated()->value());
        self::assertSame('6', $line?->available()->value());
        self::assertCount(1, $reader->allocationsForOrder($this->admin(self::A), $this->order(self::A)));
        self::assertCount(1, $reader->allocationsForLine($this->admin(self::A), $this->order(self::A), $this->orderLine(self::A)));
        self::assertCount(1, $reader->allocationsForSalesInvoice($this->admin(self::A), $this->invoice(self::A, 2)));
        self::assertCount(1, $reader->allocationsForSalesInvoiceLine($this->admin(self::A), $this->invoice(self::A, 2), $this->invoiceLine(self::A, 2)));

        $store->appendDraftRequest($this->request(self::A, 3));
        $store->appendReservation($this->reservation(self::A, 3, '6'));
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendAllocation($this->allocation(self::A, 3, '6')));
        $line = $reader->progress($this->admin(self::A), $this->order(self::A))?->lines()[0];
        self::assertSame('10', $line?->allocated()->value());
        self::assertSame('0', $line?->available()->value());
    }

    public function test_over_reservation_tenant_boundaries_direct_invoice_and_outer_rollback_are_safe(): void
    {
        $store = $this->app->make(OrderInvoicingFactStore::class);
        $reader = $this->app->make(OrderInvoicingProgressReader::class);
        $store->appendDraftRequest($this->request(self::A, 1));
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendReservation($this->reservation(self::A, 1, '6')));
        $store->appendDraftRequest($this->request(self::A, 2));
        self::assertSame(OrderInvoicingFactAppendResult::QuantityExceedsAvailable, $store->appendReservation($this->reservation(self::A, 2, '5')));
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendReservation($this->reservation(self::A, 2, '4')));
        self::assertSame('0', $reader->progress($this->admin(self::A), $this->order(self::A))?->lines()[0]->available()->value());
        self::assertNull($reader->progress($this->admin(self::A), $this->order(self::B)));
        self::assertSame([], $reader->activeReservationsForOrder($this->admin(self::B), $this->order(self::A)));

        $crossTenant = new OrderInvoiceReservation($this->reservationId(8), $this->admin(self::A), $this->requestId(1), $this->order(self::A), $this->orderLine(self::B), $this->invoice(self::A, 1), $this->invoiceLine(self::A, 1), new Quantity('1'));
        self::assertSame(OrderInvoicingFactAppendResult::NotFound, $store->appendReservation($crossTenant));
        $this->assertDatabaseHas('sales_invoices', ['id' => $this->invoice(self::A, 9)->toString(), 'source_order_id' => null]);
        self::assertSame(0, DB::table('order_invoice_draft_requests')->where('sales_invoice_id', $this->invoice(self::A, 9)->toString())->count());

        $rolledBackId = $this->requestId(7);
        try {
            $this->app->make(TransactionManager::class)->run(function () use ($store, $rolledBackId): void {
                $fact = new OrderInvoiceDraftRequest($rolledBackId, $this->admin(self::A), $this->order(self::A), $this->invoice(self::A, 3));
                self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendDraftRequest($fact));
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseMissing('order_invoice_draft_requests', ['id' => $rolledBackId->toString()]);

        $this->expectException(QueryException::class);
        DB::table('orders')->where('id', $this->order(self::A)->toString())->delete();
    }

    public function test_real_mysql_concurrent_reservations_never_exceed_ordered_quantity(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $store = $this->app->make(OrderInvoicingFactStore::class);
        $store->appendDraftRequest($this->request(self::A, 1));
        $store->appendDraftRequest($this->request(self::A, 2));
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'reserve-'), tempnam(sys_get_temp_dir(), 'reserve-')];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->app->make(OrderInvoicingFactStore::class)->appendReservation($this->reservation(self::A, $index + 1, '6'));
                    file_put_contents($file, $result->name);
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
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($results);
        self::assertSame(['Appended', 'QuantityExceedsAvailable'], $results);
        $progress = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->order(self::A));
        self::assertSame('6', $progress?->lines()[0]->reserved()->value());
        self::assertSame('4', $progress?->lines()[0]->available()->value());
        $this->removeCommittedFixtures();
        DB::beginTransaction();
    }

    public function test_multi_line_quantities_are_independent(): void
    {
        $now = now();
        $secondOrderLine = new OrderLineId(new Uuid($this->uuid(self::A, 12)));
        $secondInvoiceLine = new SalesInvoiceLineId(new Uuid($this->uuid(self::A, 72)));
        DB::table('order_lines')->insert(['id' => $secondOrderLine->toString(), 'administration_id' => self::A, 'order_id' => $this->order(self::A)->toString(), 'description' => 'Second', 'quantity' => '5', 'unit_price_amount' => '7', 'currency' => 'EUR', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sales_invoice_lines')->insert(['id' => $secondInvoiceLine->toString(), 'administration_id' => self::A, 'sales_invoice_id' => $this->invoice(self::A, 1)->toString(), 'description' => 'Second', 'quantity' => '5', 'unit_price_amount' => '7', 'currency' => 'EUR', 'tax_code_id_snapshot' => $this->tax(self::A), 'tax_code_snapshot' => 'T', 'tax_name_snapshot' => 'Tax', 'tax_rate_snapshot' => '21', 'tax_direction_snapshot' => 'output', 'tax_treatment_snapshot' => 'domestic_standard', 'vat_return_classification_snapshot' => 'domestic_standard', 'icp_classification_snapshot' => 'none', 'created_at' => $now, 'updated_at' => $now]);
        $store = $this->app->make(OrderInvoicingFactStore::class);
        $store->appendDraftRequest($this->request(self::A, 1));
        $store->appendReservation($this->reservation(self::A, 1, '4'));
        $second = new OrderInvoiceReservation($this->reservationId(8), $this->admin(self::A), $this->requestId(1), $this->order(self::A), $secondOrderLine, $this->invoice(self::A, 1), $secondInvoiceLine, new Quantity('5'));
        self::assertSame(OrderInvoicingFactAppendResult::Appended, $store->appendReservation($second));

        $lines = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->order(self::A))?->lines();
        self::assertCount(2, $lines);
        self::assertSame(['6', '0'], array_map(static fn ($line): string => $line->available()->value(), $lines ?? []));
    }

    private function seedTenant(string $admin, string $code): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => $admin, 'code' => 'WB'.$code, 'name' => 'W4B '.$code, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => $this->relation($admin), 'administration_id' => $admin, 'code' => 'R'.$code, 'display_name' => 'Customer '.$code, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => $this->customer($admin), 'administration_id' => $admin, 'relation_id' => $this->relation($admin), 'customer_number' => 'C'.$code, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('tax_codes')->insert(['id' => $this->tax($admin), 'administration_id' => $admin, 'code' => 'T'.$code, 'name' => 'Tax '.$code, 'rate' => '21', 'direction' => 'output', 'status' => 'active', 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('orders')->insert(['id' => $this->order($admin)->toString(), 'administration_id' => $admin, 'order_number' => 'O'.$code, 'customer_id' => $this->customer($admin), 'customer_relation_id_snapshot' => $this->relation($admin), 'customer_number_snapshot' => 'C'.$code, 'customer_name_snapshot' => 'Customer '.$code, 'source_quotation_id' => null, 'currency' => 'EUR', 'order_date' => '2026-08-24', 'status' => 'confirmed', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('order_lines')->insert(['id' => $this->orderLine($admin)->toString(), 'administration_id' => $admin, 'order_id' => $this->order($admin)->toString(), 'description' => 'Line '.$code, 'quantity' => '10', 'unit_price_amount' => '5', 'currency' => 'EUR', 'created_at' => $now, 'updated_at' => $now]);
    }

    private function seedInvoice(string $admin, int $sequence, bool $source): void
    {
        $now = now();
        DB::table('sales_invoices')->insert(['id' => $this->invoice($admin, $sequence)->toString(), 'administration_id' => $admin, 'sales_invoice_number' => 'F'.substr($admin, 0, 1).$sequence, 'customer_id' => $this->customer($admin), 'customer_relation_id_snapshot' => $this->relation($admin), 'customer_number_snapshot' => 'C', 'customer_name_snapshot' => 'Customer', 'invoice_address_id_snapshot' => $this->uuid($admin, 90 + $sequence), 'invoice_address_type_snapshot' => 'invoice', 'invoice_address_line_1_snapshot' => 'Street 1', 'invoice_address_line_2_snapshot' => null, 'invoice_postal_code_snapshot' => '1234AB', 'invoice_city_snapshot' => 'City', 'invoice_country_code_snapshot' => 'NL', 'source_order_id' => $source ? $this->order($admin)->toString() : null, 'currency' => 'EUR', 'invoice_date' => '2026-08-24', 'due_date' => '2026-09-24', 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sales_invoice_lines')->insert(['id' => $this->invoiceLine($admin, $sequence)->toString(), 'administration_id' => $admin, 'sales_invoice_id' => $this->invoice($admin, $sequence)->toString(), 'description' => 'Line', 'quantity' => '10', 'unit_price_amount' => '5', 'currency' => 'EUR', 'tax_code_id_snapshot' => $this->tax($admin), 'tax_code_snapshot' => 'T', 'tax_name_snapshot' => 'Tax', 'tax_rate_snapshot' => '21', 'tax_direction_snapshot' => 'output', 'tax_treatment_snapshot' => 'domestic_standard', 'vat_return_classification_snapshot' => 'domestic_standard', 'icp_classification_snapshot' => 'none', 'created_at' => $now, 'updated_at' => $now]);
    }

    private function request(string $admin, int $sequence): OrderInvoiceDraftRequest
    {
        return new OrderInvoiceDraftRequest($this->requestId($sequence), $this->admin($admin), $this->order($admin), $this->invoice($admin, $sequence));
    }

    private function reservation(string $admin, int $sequence, string $quantity): OrderInvoiceReservation
    {
        return new OrderInvoiceReservation($this->reservationId($sequence), $this->admin($admin), $this->requestId($sequence), $this->order($admin), $this->orderLine($admin), $this->invoice($admin, $sequence), $this->invoiceLine($admin, $sequence), new Quantity($quantity));
    }

    private function allocation(string $admin, int $sequence, string $quantity): OrderInvoiceAllocation
    {
        return new OrderInvoiceAllocation($this->allocationId($sequence), $this->admin($admin), $this->reservationId($sequence), $this->order($admin), $this->orderLine($admin), $this->invoice($admin, $sequence), $this->invoiceLine($admin, $sequence), new Quantity($quantity));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function order(string $admin): OrderId
    {
        return new OrderId(new Uuid($this->uuid($admin, 10)));
    }

    private function orderLine(string $admin): OrderLineId
    {
        return new OrderLineId(new Uuid($this->uuid($admin, 11)));
    }

    private function invoice(string $admin, int $sequence): SalesInvoiceId
    {
        return new SalesInvoiceId(new Uuid($this->uuid($admin, 20 + $sequence)));
    }

    private function invoiceLine(string $admin, int $sequence): SalesInvoiceLineId
    {
        return new SalesInvoiceLineId(new Uuid($this->uuid($admin, 40 + $sequence)));
    }

    private function requestId(int $sequence): OrderInvoiceDraftRequestId
    {
        return new OrderInvoiceDraftRequestId(new Uuid(sprintf('c7000000-0000-4000-8000-%012d', $sequence)));
    }

    private function reservationId(int $sequence): OrderInvoiceReservationId
    {
        return new OrderInvoiceReservationId(new Uuid(sprintf('d7000000-0000-4000-8000-%012d', $sequence)));
    }

    private function releaseId(int $sequence): OrderInvoiceReservationReleaseId
    {
        return new OrderInvoiceReservationReleaseId(new Uuid(sprintf('e7000000-0000-4000-8000-%012d', $sequence)));
    }

    private function allocationId(int $sequence): OrderInvoiceAllocationId
    {
        return new OrderInvoiceAllocationId(new Uuid(sprintf('f7000000-0000-4000-8000-%012d', $sequence)));
    }

    private function relation(string $admin): string
    {
        return $this->uuid($admin, 2);
    }

    private function customer(string $admin): string
    {
        return $this->uuid($admin, 3);
    }

    private function tax(string $admin): string
    {
        return $this->uuid($admin, 4);
    }

    private function uuid(string $admin, int $suffix): string
    {
        return substr($admin, 0, 24).sprintf('%012d', $suffix);
    }

    private function removeCommittedFixtures(): void
    {
        foreach (['order_invoice_allocations', 'order_invoice_reservation_releases', 'order_invoice_reservations', 'order_invoice_draft_requests', 'sales_invoice_lines', 'sales_invoices', 'order_lines', 'orders', 'tax_codes', 'customers', 'relations', 'administrations'] as $table) {
            DB::table($table)->delete();
        }
    }
}
