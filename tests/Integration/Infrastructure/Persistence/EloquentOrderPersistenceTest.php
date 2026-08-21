<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Sales\OrderDetailReadRepository;
use App\Application\Sales\OrderListQuery;
use App\Application\Sales\OrderListReadRepository;
use App\Application\Sales\OrderSortDirection;
use App\Application\Sales\OrderSortField;
use App\Application\Sales\OrderWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentOrderReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentOrderRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OrderLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentOrderPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'a1000000-0000-4000-8000-000000000001';

    private const B = 'b1000000-0000-4000-8000-000000000001';

    private EloquentOrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentOrderRepository;
        $this->tenant(self::A, 'A');
        $this->tenant(self::B, 'B');
    }

    public function test_all_statuses_snapshot_dates_currency_lines_and_exact_totals_roundtrip(): void
    {
        foreach (OrderStatus::cases() as $index => $status) {
            $order = $this->order($index + 1, $status);
            self::assertSame(OrderWriteResult::Success, $this->repository->create($this->admin(self::A), $order));
            $read = $this->repository->findForAdministration($this->admin(self::A), $order->id());

            self::assertNotNull($read);
            self::assertSame($status, $read->status());
            self::assertSame('Customer A', $read->customerSnapshot()?->displayName()->value());
            self::assertSame('C-A', $read->customerSnapshot()?->customerNumber()->value());
            self::assertSame('2026-08-21', $read->orderDate()->format('Y-m-d'));
            self::assertNull($read->sourceQuotationId());
            self::assertSame('EUR', $read->currency()->code());
            self::assertSame('1.2345', $read->lines()[0]->quantity()->value());
            self::assertSame('10', $read->lines()[0]->unitPrice()->amount());
            self::assertSame($order->total()->amount(), $read->total()->amount());
        }
    }

    public function test_tenant_isolation_duplicate_constraints_and_same_tenant_line_fk_are_database_safe(): void
    {
        $order = $this->order(1, OrderStatus::Draft);
        self::assertSame(OrderWriteResult::Success, $this->repository->create($this->admin(self::A), $order));
        self::assertNull($this->repository->findForAdministration($this->admin(self::B), $order->id()));
        self::assertSame(OrderWriteResult::DuplicateIdentity, $this->repository->create($this->admin(self::A), $order));
        self::assertSame(OrderWriteResult::DuplicateNumber, $this->repository->create($this->admin(self::A), $this->order(2, OrderStatus::Draft, 'O000001')));

        $this->expectException(QueryException::class);
        OrderLineRecord::query()->create(['id' => 'd1000000-0000-4000-8000-000000000099', 'administration_id' => self::B, 'order_id' => $order->id()->toString(), 'description' => 'Cross tenant', 'quantity' => '1', 'unit_price_amount' => '1', 'currency' => 'EUR']);
    }

    public function test_update_is_not_upsert_and_syncs_draft_lines_only(): void
    {
        $order = $this->order(1, OrderStatus::Draft);
        $this->repository->create($this->admin(self::A), $order);
        $order->changeOrderDate(new DateTimeImmutable('2026-08-22'));
        $order->updateLine($this->line(1, '2', '5'));
        $order->addLine($this->line(2, '1', '3'));
        self::assertSame(OrderWriteResult::Success, $this->repository->update($this->admin(self::A), $order));

        $read = $this->repository->findForAdministration($this->admin(self::A), $order->id());
        self::assertSame('2026-08-22', $read?->orderDate()->format('Y-m-d'));
        self::assertNull($read?->sourceQuotationId());
        self::assertCount(2, $read?->lines());
        self::assertSame('13', $read?->total()->amount());
        self::assertSame(OrderWriteResult::NotFound, $this->repository->update($this->admin(self::B), $order));
    }

    public function test_list_and_detail_are_tenant_filtered_searchable_sortable_and_paginated_without_duplicates(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->repository->create($this->admin(self::A), $this->order($i, $i === 1 ? OrderStatus::Confirmed : OrderStatus::Draft, sprintf('O%06d', $i)));
        }
        $reads = new EloquentOrderReadRepository($this->repository);
        $page = $reads->search(new OrderListQuery($this->admin(self::A), sortField: OrderSortField::Number, sortDirection: OrderSortDirection::Ascending));
        self::assertSame(26, $page->total());
        self::assertCount(25, $page->items());
        self::assertSame('O000001', $page->items()[0]->number()->value());
        self::assertSame($this->order(1, OrderStatus::Confirmed)->total()->amount(), $page->items()[0]->netTotal()->amount());
        self::assertCount(1, $reads->search(new OrderListQuery($this->admin(self::A), search: 'O000001', status: OrderStatus::Confirmed))->items());
        self::assertSame(0, $reads->search(new OrderListQuery($this->admin(self::B)))->total());

        $detail = $reads->find($this->admin(self::A), $this->qid(1));
        self::assertSame('Customer A', $detail?->customer()->displayName()->value());
        self::assertCount(1, $detail?->lines());
        self::assertNull($reads->find($this->admin(self::B), $this->qid(1)));
        self::assertInstanceOf(EloquentOrderReadRepository::class, $this->app->make(OrderListReadRepository::class));
        self::assertInstanceOf(EloquentOrderReadRepository::class, $this->app->make(OrderDetailReadRepository::class));
    }

    private function order(int $id, OrderStatus $status, ?string $number = null): Order
    {
        return Order::reconstitute($this->qid($id), new OrderNumber($number ?? sprintf('O%06d', $id)), $this->admin(self::A), $this->customer(self::A), new Currency('EUR'), new DateTimeImmutable('2026-08-21'), null, $status, [$this->line($id)], $this->snapshot(self::A));
    }

    private function line(int $id, string $quantity = '1.2345', string $amount = '10'): OrderLine
    {
        return new OrderLine(new OrderLineId(new Uuid(sprintf('d1000000-0000-4000-8000-%012d', $id))), new LineDescription('Consulting'), new Quantity($quantity), new Money($amount, new Currency('EUR')));
    }

    private function qid(int $id): OrderId
    {
        return new OrderId(new Uuid(sprintf('c1000000-0000-4000-8000-%012d', $id)));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function customer(string $tenant): CustomerId
    {
        return new CustomerId(new Uuid($tenant === self::A ? 'a3000000-0000-4000-8000-000000000001' : 'b3000000-0000-4000-8000-000000000001'));
    }

    private function relation(string $tenant): RelationId
    {
        return new RelationId(new Uuid($tenant === self::A ? 'a2000000-0000-4000-8000-000000000001' : 'b2000000-0000-4000-8000-000000000001'));
    }

    private function snapshot(string $tenant): SalesCustomerSnapshot
    {
        return new SalesCustomerSnapshot($this->customer($tenant), $this->relation($tenant), new CustomerNumber('C-'.strtoupper($tenant[0])), new DisplayName('Customer '.strtoupper($tenant[0])));
    }

    private function tenant(string $id, string $suffix): void
    {
        AdministrationRecord::query()->create(['id' => $id, 'code' => 'QT-'.$suffix, 'name' => 'Order tenant '.$suffix, 'base_currency' => 'EUR', 'status' => 'active']);
        RelationRecord::query()->create(['id' => $this->relation($id)->toString(), 'administration_id' => $id, 'code' => 'REL-'.$suffix, 'display_name' => 'Customer '.$suffix, 'active' => true]);
        CustomerRecord::query()->create(['id' => $this->customer($id)->toString(), 'administration_id' => $id, 'relation_id' => $this->relation($id)->toString(), 'customer_number' => 'C-'.$suffix, 'active' => true]);
    }
}
