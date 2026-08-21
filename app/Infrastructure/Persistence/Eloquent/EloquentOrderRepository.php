<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\OrderCreator;
use App\Application\Sales\OrderReadRepository;
use App\Application\Sales\OrderUpdater;
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
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\OrderLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OrderRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentOrderRepository implements OrderCreator, OrderReadRepository, OrderUpdater
{
    public function findForAdministration(AdministrationId $administrationId, OrderId $orderId): ?Order
    {
        $record = OrderRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->whereKey($orderId->toString())
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function create(AdministrationId $administrationId, Order $order): OrderWriteResult
    {
        try {
            OrderRecord::query()->create($this->headerAttributes($administrationId, $order));
            $this->insertLines($administrationId, $order);
        } catch (QueryException $exception) {
            $conflict = $this->classifyCreateConflict($administrationId, $order);
            if ($conflict === null) {
                throw $exception;
            }

            return $conflict;
        }

        return OrderWriteResult::Success;
    }

    public function update(AdministrationId $administrationId, Order $order): OrderWriteResult
    {
        $record = OrderRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->whereKey($order->id()->toString())
            ->lockForUpdate()
            ->first();
        if ($record === null) {
            return OrderWriteResult::NotFound;
        }
        $this->assertImmutableContext($record, $order);

        $attributes = $this->headerAttributes($administrationId, $order);
        unset($attributes['id'], $attributes['administration_id'], $attributes['order_number'], $attributes['customer_id'], $attributes['customer_relation_id_snapshot'], $attributes['customer_number_snapshot'], $attributes['customer_name_snapshot'], $attributes['source_quotation_id'], $attributes['currency']);
        $record->fill($attributes)->save();
        $this->syncLines($administrationId, $order);

        return OrderWriteResult::Success;
    }

    private function classifyCreateConflict(AdministrationId $administrationId, Order $order): ?OrderWriteResult
    {
        if (OrderRecord::query()->whereKey($order->id()->toString())->exists()) {
            return OrderWriteResult::DuplicateIdentity;
        }
        if (OrderRecord::query()->where('administration_id', $administrationId->toString())->where('order_number', $order->number()->value())->exists()) {
            return OrderWriteResult::DuplicateNumber;
        }

        return null;
    }

    private function assertImmutableContext(OrderRecord $record, Order $order): void
    {
        $snapshot = $order->customerSnapshot();
        if ($snapshot === null
            || $record->getAttribute('order_number') !== $order->number()->value()
            || $record->getAttribute('customer_id') !== $order->customerId()->toString()
            || $record->getAttribute('customer_relation_id_snapshot') !== $snapshot->relationId()->toString()
            || $record->getAttribute('customer_number_snapshot') !== $snapshot->customerNumber()->toString()
            || $record->getAttribute('customer_name_snapshot') !== $snapshot->displayName()->toString()
            || $record->getAttribute('source_quotation_id') !== $order->sourceQuotationId()?->toString()
            || $record->getAttribute('currency') !== $order->currency()->code()) {
            throw new DomainException('Order immutable context cannot change.');
        }
    }

    /** @return array<string, mixed> */
    private function headerAttributes(AdministrationId $administrationId, Order $order): array
    {
        $snapshot = $order->customerSnapshot();
        if ($snapshot === null) {
            throw new DomainException('Persistent Order requires a Customer snapshot.');
        }

        return [
            'id' => $order->id()->toString(),
            'administration_id' => $administrationId->toString(),
            'order_number' => $order->number()->value(),
            'customer_id' => $order->customerId()->toString(),
            'customer_relation_id_snapshot' => $snapshot->relationId()->toString(),
            'customer_number_snapshot' => $snapshot->customerNumber()->toString(),
            'customer_name_snapshot' => $snapshot->displayName()->toString(),
            'source_quotation_id' => $order->sourceQuotationId()?->toString(),
            'currency' => $order->currency()->code(),
            'order_date' => $order->orderDate()->format('Y-m-d'),
            'status' => $order->status()->value,
        ];
    }

    private function insertLines(AdministrationId $administrationId, Order $order): void
    {
        foreach ($order->lines() as $line) {
            OrderLineRecord::query()->create($this->lineAttributes($administrationId, $order, $line));
        }
    }

    private function syncLines(AdministrationId $administrationId, Order $order): void
    {
        $ids = array_map(static fn (OrderLine $line): string => $line->id()->toString(), $order->lines());
        $query = OrderLineRecord::query()->where('administration_id', $administrationId->toString())->where('order_id', $order->id()->toString());
        $ids === [] ? $query->delete() : $query->whereNotIn('id', $ids)->delete();
        foreach ($order->lines() as $line) {
            $record = OrderLineRecord::query()->whereKey($line->id()->toString())->where('administration_id', $administrationId->toString())->where('order_id', $order->id()->toString())->first();
            if ($record === null) {
                OrderLineRecord::query()->create($this->lineAttributes($administrationId, $order, $line));
            } else {
                $record->fill($this->lineAttributes($administrationId, $order, $line))->save();
            }
        }
    }

    /** @return array<string, mixed> */
    private function lineAttributes(AdministrationId $administrationId, Order $order, OrderLine $line): array
    {
        return ['id' => $line->id()->toString(), 'administration_id' => $administrationId->toString(), 'order_id' => $order->id()->toString(), 'description' => $line->description()->value(), 'quantity' => $line->quantity()->value(), 'unit_price_amount' => $line->unitPrice()->amount(), 'currency' => $line->unitPrice()->currency()->code()];
    }

    private function hydrate(OrderRecord $record): Order
    {
        $currency = new Currency($record->getAttribute('currency'));
        $lines = OrderLineRecord::query()->where('administration_id', $record->getAttribute('administration_id'))->where('order_id', $record->getAttribute('id'))->orderBy('id')->get()
            ->map(static fn (OrderLineRecord $line): OrderLine => new OrderLine(new OrderLineId(new Uuid($line->getAttribute('id'))), new LineDescription($line->getAttribute('description')), new Quantity($line->getAttribute('quantity')), new Money($line->getAttribute('unit_price_amount'), $currency)))->all();
        $source = $record->getAttribute('source_quotation_id');

        return Order::reconstitute(
            new OrderId(new Uuid($record->getAttribute('id'))), new OrderNumber($record->getAttribute('order_number')),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))), new CustomerId(new Uuid($record->getAttribute('customer_id'))), $currency,
            new DateTimeImmutable($record->getAttribute('order_date')), $source === null ? null : new QuotationId(new Uuid($source)),
            OrderStatus::from($record->getAttribute('status')), $lines,
            new SalesCustomerSnapshot(new CustomerId(new Uuid($record->getAttribute('customer_id'))), new RelationId(new Uuid($record->getAttribute('customer_relation_id_snapshot'))), new CustomerNumber($record->getAttribute('customer_number_snapshot')), new DisplayName($record->getAttribute('customer_name_snapshot'))),
        );
    }
}
