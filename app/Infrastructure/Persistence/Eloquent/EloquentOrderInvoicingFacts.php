<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\OrderInvoiceDraftRequestReader;
use App\Application\Sales\OrderInvoicingAllocationView;
use App\Application\Sales\OrderInvoicingFactAppendResult;
use App\Application\Sales\OrderInvoicingFactStore;
use App\Application\Sales\OrderInvoicingOrderLocker;
use App\Application\Sales\OrderInvoicingProgress;
use App\Application\Sales\OrderInvoicingProgressLine;
use App\Application\Sales\OrderInvoicingProgressReader;
use App\Application\Sales\OrderInvoicingReservationView;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\OrderInvoiceAllocation;
use App\Domain\Sales\Entities\OrderInvoiceDraftRequest;
use App\Domain\Sales\Entities\OrderInvoiceReservation;
use App\Domain\Sales\Entities\OrderInvoiceReservationRelease;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceAllocationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\OrderInvoiceQuantityBalance;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentOrderInvoicingFacts implements OrderInvoiceDraftRequestReader, OrderInvoicingFactStore, OrderInvoicingOrderLocker, OrderInvoicingProgressReader
{
    public function find(AdministrationId $administrationId, OrderInvoiceDraftRequestId $requestId): ?OrderInvoiceDraftRequest
    {
        $row = DB::table('order_invoice_draft_requests')->where('administration_id', $administrationId->toString())->where('id', $requestId->toString())->first();

        return $row === null ? null : new OrderInvoiceDraftRequest(
            new OrderInvoiceDraftRequestId(new Uuid($row->id)),
            $administrationId,
            new OrderId(new Uuid($row->order_id)),
            new SalesInvoiceId(new Uuid($row->sales_invoice_id)),
        );
    }

    public function appendDraftRequest(OrderInvoiceDraftRequest $request): OrderInvoicingFactAppendResult
    {
        return DB::transaction(function () use ($request): OrderInvoicingFactAppendResult {
            if (! $this->lock($request->administrationId(), $request->orderId())) {
                return OrderInvoicingFactAppendResult::NotFound;
            }
            $existing = DB::table('order_invoice_draft_requests')->where('id', $request->id()->toString())->first();
            if ($existing !== null) {
                return $this->draftRequestMatches($existing, $request) ? OrderInvoicingFactAppendResult::AlreadyExists : OrderInvoicingFactAppendResult::InvalidFactState;
            }
            if (! DB::table('sales_invoices')->where('administration_id', $request->administrationId()->toString())->where('id', $request->salesInvoiceId()->toString())->where('source_order_id', $request->orderId()->toString())->exists()) {
                return OrderInvoicingFactAppendResult::NotFound;
            }
            try {
                DB::table('order_invoice_draft_requests')->insert([
                    'id' => $request->id()->toString(), 'administration_id' => $request->administrationId()->toString(),
                    'order_id' => $request->orderId()->toString(), 'sales_invoice_id' => $request->salesInvoiceId()->toString(), 'created_at' => now(),
                ]);
            } catch (QueryException) {
                return OrderInvoicingFactAppendResult::PersistenceConflict;
            }

            return OrderInvoicingFactAppendResult::Appended;
        });
    }

    public function appendReservation(OrderInvoiceReservation $reservation): OrderInvoicingFactAppendResult
    {
        return DB::transaction(function () use ($reservation): OrderInvoicingFactAppendResult {
            if (! $this->lock($reservation->administrationId(), $reservation->orderId())) {
                return OrderInvoicingFactAppendResult::NotFound;
            }
            $existing = DB::table('order_invoice_reservations')->where('id', $reservation->id()->toString())->first();
            if ($existing !== null) {
                return $this->reservationMatches($existing, $reservation) ? OrderInvoicingFactAppendResult::AlreadyExists : OrderInvoicingFactAppendResult::InvalidFactState;
            }
            if (! $this->reservationContextExists($reservation)) {
                return OrderInvoicingFactAppendResult::NotFound;
            }
            $progress = $this->progress($reservation->administrationId(), $reservation->orderId());
            $line = $progress === null ? null : collect($progress->lines())->first(fn (OrderInvoicingProgressLine $item): bool => $item->orderLineId()->equals($reservation->orderLineId()));
            if (! $line instanceof OrderInvoicingProgressLine) {
                return OrderInvoicingFactAppendResult::NotFound;
            }
            if ($line->available()->isLessThan($reservation->quantity())) {
                return OrderInvoicingFactAppendResult::QuantityExceedsAvailable;
            }
            try {
                DB::table('order_invoice_reservations')->insert([
                    'id' => $reservation->id()->toString(), 'administration_id' => $reservation->administrationId()->toString(),
                    'draft_request_id' => $reservation->draftRequestId()->toString(), 'order_id' => $reservation->orderId()->toString(),
                    'order_line_id' => $reservation->orderLineId()->toString(), 'sales_invoice_id' => $reservation->salesInvoiceId()->toString(),
                    'sales_invoice_line_id' => $reservation->salesInvoiceLineId()->toString(), 'quantity' => $reservation->quantity()->value(), 'created_at' => now(),
                ]);
            } catch (QueryException) {
                return OrderInvoicingFactAppendResult::PersistenceConflict;
            }

            return OrderInvoicingFactAppendResult::Appended;
        });
    }

    public function appendRelease(OrderInvoiceReservationRelease $release): OrderInvoicingFactAppendResult
    {
        return DB::transaction(function () use ($release): OrderInvoicingFactAppendResult {
            $reservation = DB::table('order_invoice_reservations')->where('administration_id', $release->administrationId()->toString())->where('id', $release->reservationId()->toString())->first();
            if ($reservation === null || ! $this->lock($release->administrationId(), new OrderId(new Uuid($reservation->order_id)))) {
                return OrderInvoicingFactAppendResult::NotFound;
            }
            $existing = DB::table('order_invoice_reservation_releases')->where('administration_id', $release->administrationId()->toString())->where('reservation_id', $release->reservationId()->toString())->first();
            if ($existing !== null) {
                return $existing->id === $release->id()->toString() ? OrderInvoicingFactAppendResult::AlreadyExists : OrderInvoicingFactAppendResult::InvalidFactState;
            }
            if (DB::table('order_invoice_allocations')->where('administration_id', $release->administrationId()->toString())->where('reservation_id', $release->reservationId()->toString())->exists()) {
                return OrderInvoicingFactAppendResult::InvalidFactState;
            }
            try {
                DB::table('order_invoice_reservation_releases')->insert(['id' => $release->id()->toString(), 'administration_id' => $release->administrationId()->toString(), 'reservation_id' => $release->reservationId()->toString(), 'created_at' => now()]);
            } catch (QueryException) {
                return OrderInvoicingFactAppendResult::PersistenceConflict;
            }

            return OrderInvoicingFactAppendResult::Appended;
        });
    }

    public function appendAllocation(OrderInvoiceAllocation $allocation): OrderInvoicingFactAppendResult
    {
        return DB::transaction(function () use ($allocation): OrderInvoicingFactAppendResult {
            if (! $this->lock($allocation->administrationId(), $allocation->orderId())) {
                return OrderInvoicingFactAppendResult::NotFound;
            }
            $existing = DB::table('order_invoice_allocations')->where('id', $allocation->id()->toString())->first();
            if ($existing !== null) {
                return $this->allocationMatches($existing, $allocation) ? OrderInvoicingFactAppendResult::AlreadyExists : OrderInvoicingFactAppendResult::InvalidFactState;
            }
            $reservation = DB::table('order_invoice_reservations')->where('administration_id', $allocation->administrationId()->toString())->where('id', $allocation->reservationId()->toString())->first();
            if ($reservation === null || ! $this->allocationMatchesReservation($allocation, $reservation)) {
                return OrderInvoicingFactAppendResult::InvalidFactState;
            }
            if (DB::table('order_invoice_reservation_releases')->where('administration_id', $allocation->administrationId()->toString())->where('reservation_id', $allocation->reservationId()->toString())->exists()
                || DB::table('order_invoice_allocations')->where('administration_id', $allocation->administrationId()->toString())->where('reservation_id', $allocation->reservationId()->toString())->exists()) {
                return OrderInvoicingFactAppendResult::InvalidFactState;
            }
            try {
                DB::table('order_invoice_allocations')->insert([
                    'id' => $allocation->id()->toString(), 'administration_id' => $allocation->administrationId()->toString(), 'reservation_id' => $allocation->reservationId()->toString(),
                    'order_id' => $allocation->orderId()->toString(), 'order_line_id' => $allocation->orderLineId()->toString(), 'sales_invoice_id' => $allocation->salesInvoiceId()->toString(),
                    'sales_invoice_line_id' => $allocation->salesInvoiceLineId()->toString(), 'quantity' => $allocation->quantity()->value(), 'created_at' => now(),
                ]);
            } catch (QueryException) {
                return OrderInvoicingFactAppendResult::PersistenceConflict;
            }

            return OrderInvoicingFactAppendResult::Appended;
        });
    }

    public function lock(AdministrationId $administrationId, OrderId $orderId): bool
    {
        return DB::table('orders')->where('administration_id', $administrationId->toString())->where('id', $orderId->toString())->lockForUpdate()->exists();
    }

    public function progress(AdministrationId $administrationId, OrderId $orderId): ?OrderInvoicingProgress
    {
        $order = DB::table('orders')->where('administration_id', $administrationId->toString())->where('id', $orderId->toString())->first();
        if ($order === null) {
            return null;
        }
        $lines = DB::table('order_lines')->where('administration_id', $administrationId->toString())->where('order_id', $orderId->toString())->orderBy('id')->get()->map(function (stdClass $line) use ($administrationId, $orderId): OrderInvoicingProgressLine {
            $reserved = OrderInvoiceQuantityBalance::zero();
            foreach ($this->activeReservationQuery($administrationId)->where('r.order_id', $orderId->toString())->where('r.order_line_id', $line->id)->get(['r.quantity']) as $fact) {
                $reserved = $reserved->add(new Quantity($fact->quantity));
            }
            $allocated = OrderInvoiceQuantityBalance::zero();
            foreach (DB::table('order_invoice_allocations')->where('administration_id', $administrationId->toString())->where('order_id', $orderId->toString())->where('order_line_id', $line->id)->get(['quantity']) as $fact) {
                $allocated = $allocated->add(new Quantity($fact->quantity));
            }
            $ordered = new Quantity($line->quantity);
            $available = OrderInvoiceQuantityBalance::fromQuantity($ordered)->subtract($reserved)->subtract($allocated);

            return new OrderInvoicingProgressLine(new OrderLineId(new Uuid($line->id)), $ordered, $reserved, $allocated, $available);
        })->all();

        return new OrderInvoicingProgress($orderId, OrderStatus::from($order->status), $lines);
    }

    public function activeReservationsForOrder(AdministrationId $administrationId, OrderId $orderId): array
    {
        return $this->reservationViews($this->activeReservationQuery($administrationId)->where('r.order_id', $orderId->toString()));
    }

    public function activeReservationsForLine(AdministrationId $administrationId, OrderId $orderId, OrderLineId $lineId): array
    {
        return $this->reservationViews($this->activeReservationQuery($administrationId)->where('r.order_id', $orderId->toString())->where('r.order_line_id', $lineId->toString()));
    }

    public function reservationsForDraftRequest(AdministrationId $administrationId, OrderInvoiceDraftRequestId $requestId): array
    {
        return $this->reservationViews(DB::table('order_invoice_reservations as r')->where('r.administration_id', $administrationId->toString())->where('r.draft_request_id', $requestId->toString()));
    }

    public function reservationsForSalesInvoice(AdministrationId $administrationId, SalesInvoiceId $invoiceId): array
    {
        return $this->reservationViews(DB::table('order_invoice_reservations as r')->where('r.administration_id', $administrationId->toString())->where('r.sales_invoice_id', $invoiceId->toString()));
    }

    public function allocationsForOrder(AdministrationId $administrationId, OrderId $orderId): array
    {
        return $this->allocationViews(DB::table('order_invoice_allocations')->where('administration_id', $administrationId->toString())->where('order_id', $orderId->toString()));
    }

    public function allocationsForLine(AdministrationId $administrationId, OrderId $orderId, OrderLineId $lineId): array
    {
        return $this->allocationViews(DB::table('order_invoice_allocations')->where('administration_id', $administrationId->toString())->where('order_id', $orderId->toString())->where('order_line_id', $lineId->toString()));
    }

    public function allocationsForSalesInvoice(AdministrationId $administrationId, SalesInvoiceId $invoiceId): array
    {
        return $this->allocationViews(DB::table('order_invoice_allocations')->where('administration_id', $administrationId->toString())->where('sales_invoice_id', $invoiceId->toString()));
    }

    public function allocationsForSalesInvoiceLine(AdministrationId $administrationId, SalesInvoiceId $invoiceId, SalesInvoiceLineId $lineId): array
    {
        return $this->allocationViews(DB::table('order_invoice_allocations')->where('administration_id', $administrationId->toString())->where('sales_invoice_id', $invoiceId->toString())->where('sales_invoice_line_id', $lineId->toString()));
    }

    private function activeReservationQuery(AdministrationId $administrationId): Builder
    {
        return DB::table('order_invoice_reservations as r')->where('r.administration_id', $administrationId->toString())
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('order_invoice_reservation_releases as rel')->whereColumn('rel.administration_id', 'r.administration_id')->whereColumn('rel.reservation_id', 'r.id'))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('order_invoice_allocations as a')->whereColumn('a.administration_id', 'r.administration_id')->whereColumn('a.reservation_id', 'r.id'));
    }

    private function reservationViews(Builder $query): array
    {
        return $query->orderBy('r.id')->get(['r.*'])->map(static fn (stdClass $row): OrderInvoicingReservationView => new OrderInvoicingReservationView(new OrderInvoiceReservationId(new Uuid($row->id)), new OrderInvoiceDraftRequestId(new Uuid($row->draft_request_id)), new OrderId(new Uuid($row->order_id)), new OrderLineId(new Uuid($row->order_line_id)), new SalesInvoiceId(new Uuid($row->sales_invoice_id)), new SalesInvoiceLineId(new Uuid($row->sales_invoice_line_id)), new Quantity($row->quantity)))->all();
    }

    private function allocationViews(Builder $query): array
    {
        return $query->orderBy('id')->get()->map(static fn (stdClass $row): OrderInvoicingAllocationView => new OrderInvoicingAllocationView(new OrderInvoiceAllocationId(new Uuid($row->id)), new OrderInvoiceReservationId(new Uuid($row->reservation_id)), new OrderId(new Uuid($row->order_id)), new OrderLineId(new Uuid($row->order_line_id)), new SalesInvoiceId(new Uuid($row->sales_invoice_id)), new SalesInvoiceLineId(new Uuid($row->sales_invoice_line_id)), new Quantity($row->quantity)))->all();
    }

    private function reservationContextExists(OrderInvoiceReservation $reservation): bool
    {
        return DB::table('order_invoice_draft_requests')->where('administration_id', $reservation->administrationId()->toString())->where('id', $reservation->draftRequestId()->toString())->where('order_id', $reservation->orderId()->toString())->where('sales_invoice_id', $reservation->salesInvoiceId()->toString())->exists()
            && DB::table('order_lines')->where('administration_id', $reservation->administrationId()->toString())->where('order_id', $reservation->orderId()->toString())->where('id', $reservation->orderLineId()->toString())->exists()
            && DB::table('sales_invoice_lines')->where('administration_id', $reservation->administrationId()->toString())->where('sales_invoice_id', $reservation->salesInvoiceId()->toString())->where('id', $reservation->salesInvoiceLineId()->toString())->exists();
    }

    private function draftRequestMatches(stdClass $row, OrderInvoiceDraftRequest $fact): bool
    {
        return $row->administration_id === $fact->administrationId()->toString() && $row->order_id === $fact->orderId()->toString() && $row->sales_invoice_id === $fact->salesInvoiceId()->toString();
    }

    private function reservationMatches(stdClass $row, OrderInvoiceReservation $fact): bool
    {
        return $row->administration_id === $fact->administrationId()->toString() && $row->draft_request_id === $fact->draftRequestId()->toString() && $row->order_id === $fact->orderId()->toString() && $row->order_line_id === $fact->orderLineId()->toString() && $row->sales_invoice_id === $fact->salesInvoiceId()->toString() && $row->sales_invoice_line_id === $fact->salesInvoiceLineId()->toString() && $row->quantity === $fact->quantity()->value();
    }

    private function allocationMatches(stdClass $row, OrderInvoiceAllocation $fact): bool
    {
        return $row->administration_id === $fact->administrationId()->toString() && $row->reservation_id === $fact->reservationId()->toString() && $row->order_id === $fact->orderId()->toString() && $row->order_line_id === $fact->orderLineId()->toString() && $row->sales_invoice_id === $fact->salesInvoiceId()->toString() && $row->sales_invoice_line_id === $fact->salesInvoiceLineId()->toString() && $row->quantity === $fact->quantity()->value();
    }

    private function allocationMatchesReservation(OrderInvoiceAllocation $fact, stdClass $row): bool
    {
        return $row->administration_id === $fact->administrationId()->toString() && $row->order_id === $fact->orderId()->toString() && $row->order_line_id === $fact->orderLineId()->toString() && $row->sales_invoice_id === $fact->salesInvoiceId()->toString() && $row->sales_invoice_line_id === $fact->salesInvoiceLineId()->toString() && $row->quantity === $fact->quantity()->value();
    }
}
