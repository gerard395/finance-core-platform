<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\OrderDetail;
use App\Application\Sales\OrderDetailReadRepository;
use App\Application\Sales\OrderListItem;
use App\Application\Sales\OrderListQuery;
use App\Application\Sales\OrderListReadRepository;
use App\Application\Sales\OrderSortField;
use App\Application\Sales\PaginatedOrderList;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\OrderRecord;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentOrderReadRepository implements OrderDetailReadRepository, OrderListReadRepository
{
    public function __construct(private EloquentOrderRepository $orders) {}

    public function find(AdministrationId $administrationId, OrderId $orderId): ?OrderDetail
    {
        $order = $this->orders->findForAdministration($administrationId, $orderId);

        return $order === null ? null : OrderDetail::fromOrder($order);
    }

    public function search(OrderListQuery $query): PaginatedOrderList
    {
        $builder = OrderRecord::query()->where('administration_id', $query->administrationId()->toString());
        $this->applyFilters($builder, $query);
        $total = (clone $builder)->count();
        $column = match ($query->sortField()) {
            OrderSortField::Number => 'order_number',
            OrderSortField::CustomerName => 'customer_name_snapshot',
            OrderSortField::OrderDate => 'order_date',
            OrderSortField::SourceQuotation => 'source_quotation_id',
            OrderSortField::Status => 'status',
        };
        $records = $builder->orderBy($column, $query->sortDirection()->value)->orderBy('id')->forPage($query->page(), $query->perPage())->get();
        $items = [];
        foreach ($records as $record) {
            $order = $this->orders->findForAdministration($query->administrationId(), new OrderId(new Uuid($record->getAttribute('id'))));
            if ($order === null || $order->customerSnapshot() === null) {
                continue;
            }
            $items[] = new OrderListItem($order->id(), $order->number(), new DisplayName($record->getAttribute('customer_name_snapshot')), $order->orderDate(), $order->status(), $order->currency(), $order->total(), $order->sourceQuotationId());
        }

        return new PaginatedOrderList($items, $query->page(), $query->perPage(), $total);
    }

    private function applyFilters(Builder $builder, OrderListQuery $query): void
    {
        if ($query->status() !== null) {
            $builder->where('status', $query->status()->value);
        }
        if ($query->customerId() !== null) {
            $builder->where('customer_id', $query->customerId()->toString());
        }
        if ($query->dateFrom() !== null) {
            $builder->whereDate('order_date', '>=', $query->dateFrom()->format('Y-m-d'));
        }
        if ($query->dateTo() !== null) {
            $builder->whereDate('order_date', '<=', $query->dateTo()->format('Y-m-d'));
        }
        if ($query->search() !== null) {
            $pattern = '%'.addcslashes($query->search(), '\\%_').'%';
            $builder->where(static fn (Builder $search) => $search->whereLike('order_number', $pattern, caseSensitive: false)->orWhereLike('customer_name_snapshot', $pattern, caseSensitive: false));
        }
    }
}
