<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\OrderInvoiceAllocation;
use App\Domain\Sales\Entities\OrderInvoiceReservationRelease;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use DomainException;
use RuntimeException;

final readonly class OrderDerivedSalesInvoiceLifecycle
{
    public function __construct(
        private TransactionManager $transactions,
        private OrderInvoicingSource $orders,
        private SalesInvoicePostingSource $invoices,
        private OrderInvoiceDraftRequestReader $requests,
        private OrderInvoicingProgressReader $progress,
        private OrderInvoicingFactStore $facts,
        private OrderInvoiceLifecycleIdentityGenerator $identities,
        private OrderUpdater $orderUpdater,
        private SalesInvoiceUpdater $invoiceUpdater,
        private SalesInvoiceReadinessChecker $readiness,
    ) {}

    public function finalize(AdministrationId $administrationId, SalesInvoiceId $invoiceId, OrderId $sourceOrderId): SalesInvoiceWriteResult
    {
        return $this->execute(function () use ($administrationId, $invoiceId, $sourceOrderId): SalesInvoiceWriteResult {
            [$invoice, $order] = $this->lockContext($administrationId, $invoiceId, $sourceOrderId);
            if (! $invoice instanceof SalesInvoice || $order === null) {
                return SalesInvoiceWriteResult::NotFound;
            }
            $validation = $this->validateFacts($administrationId, $invoice, $sourceOrderId);
            if ($validation instanceof SalesInvoiceWriteResult) {
                return $validation;
            }
            [$reservations, $active, $allocations] = $validation;
            if ($invoice->status() === SalesInvoiceStatus::Finalized) {
                return $this->allocationsMatchReservations($reservations, $allocations)
                    ? SalesInvoiceWriteResult::Success
                    : SalesInvoiceWriteResult::ReservationStateInconsistent;
            }
            if ($invoice->status() !== SalesInvoiceStatus::Draft) {
                return SalesInvoiceWriteResult::InvalidState;
            }
            if ($allocations !== [] || count($active) !== count($reservations)) {
                return SalesInvoiceWriteResult::ReservationStateInconsistent;
            }
            if ($this->readiness->check($invoice)->status() !== SalesInvoiceReadinessStatus::Ready) {
                return SalesInvoiceWriteResult::InvalidState;
            }
            foreach ($active as $reservation) {
                $result = $this->facts->appendAllocation(new OrderInvoiceAllocation(
                    $this->identities->allocationId(), $administrationId, $reservation->id, $reservation->orderId,
                    $reservation->orderLineId, $reservation->salesInvoiceId, $reservation->salesInvoiceLineId, $reservation->quantity,
                ));
                if ($result !== OrderInvoicingFactAppendResult::Appended) {
                    throw new OrderInvoiceLifecycleFailure($result === OrderInvoicingFactAppendResult::InvalidFactState ? SalesInvoiceWriteResult::AllocationConflict : SalesInvoiceWriteResult::PersistenceConflict);
                }
            }
            $current = $this->progress->progress($administrationId, $sourceOrderId);
            if ($current === null || $current->lines() === []) {
                throw new OrderInvoiceLifecycleFailure(SalesInvoiceWriteResult::ReservationStateInconsistent);
            }
            $fully = true;
            $allocated = false;
            foreach ($current->lines() as $line) {
                $fully = $fully && $line->allocated()->value() === $line->ordered()->value();
                $allocated = $allocated || ! $line->allocated()->isZero();
            }
            if (! $allocated) {
                throw new OrderInvoiceLifecycleFailure(SalesInvoiceWriteResult::ReservationStateInconsistent);
            }
            $fully ? $order->markFullyInvoiced() : $order->markPartiallyInvoiced();
            $invoice->finalize();
            if ($this->orderUpdater->update($administrationId, $order) !== OrderWriteResult::Success) {
                throw new OrderInvoiceLifecycleFailure(SalesInvoiceWriteResult::PersistenceConflict);
            }
            if ($this->invoiceUpdater->update($administrationId, $invoice) !== SalesInvoiceWriteResult::Success) {
                throw new OrderInvoiceLifecycleFailure(SalesInvoiceWriteResult::PersistenceConflict);
            }

            return SalesInvoiceWriteResult::Success;
        });
    }

    public function cancel(AdministrationId $administrationId, SalesInvoiceId $invoiceId, OrderId $sourceOrderId): SalesInvoiceWriteResult
    {
        return $this->execute(function () use ($administrationId, $invoiceId, $sourceOrderId): SalesInvoiceWriteResult {
            [$invoice, $order] = $this->lockContext($administrationId, $invoiceId, $sourceOrderId);
            if (! $invoice instanceof SalesInvoice || $order === null) {
                return SalesInvoiceWriteResult::NotFound;
            }
            $validation = $this->validateFacts($administrationId, $invoice, $sourceOrderId);
            if ($validation instanceof SalesInvoiceWriteResult) {
                return $validation;
            }
            [$reservations, $active, $allocations] = $validation;
            if ($invoice->status() === SalesInvoiceStatus::Cancelled) {
                return ($allocations === [] && $active === []) || $this->allocationsMatchReservations($reservations, $allocations)
                    ? SalesInvoiceWriteResult::Success
                    : SalesInvoiceWriteResult::ReservationStateInconsistent;
            }
            if ($invoice->status() === SalesInvoiceStatus::Finalized) {
                if ($active !== [] || ! $this->allocationsMatchReservations($reservations, $allocations)) {
                    return SalesInvoiceWriteResult::ReservationStateInconsistent;
                }
                $invoice->cancel();
            } elseif ($invoice->status() === SalesInvoiceStatus::Draft) {
                if ($allocations !== [] || count($active) !== count($reservations)) {
                    return SalesInvoiceWriteResult::ReservationStateInconsistent;
                }
                foreach ($active as $reservation) {
                    $result = $this->facts->appendRelease(new OrderInvoiceReservationRelease($this->identities->releaseId(), $administrationId, $reservation->id));
                    if ($result !== OrderInvoicingFactAppendResult::Appended) {
                        throw new OrderInvoiceLifecycleFailure($result === OrderInvoicingFactAppendResult::InvalidFactState ? SalesInvoiceWriteResult::AllocationConflict : SalesInvoiceWriteResult::PersistenceConflict);
                    }
                }
                $invoice->cancel();
            } else {
                return SalesInvoiceWriteResult::InvalidState;
            }
            if ($this->invoiceUpdater->update($administrationId, $invoice) !== SalesInvoiceWriteResult::Success) {
                throw new OrderInvoiceLifecycleFailure(SalesInvoiceWriteResult::PersistenceConflict);
            }

            return SalesInvoiceWriteResult::Success;
        });
    }

    private function execute(callable $operation): SalesInvoiceWriteResult
    {
        try {
            return $this->transactions->run($operation);
        } catch (OrderInvoiceLifecycleFailure $failure) {
            return $failure->result;
        } catch (DomainException) {
            return SalesInvoiceWriteResult::ReservationStateInconsistent;
        }
    }

    private function lockContext(AdministrationId $administrationId, SalesInvoiceId $invoiceId, OrderId $sourceOrderId): array
    {
        $order = $this->orders->findLockedForAdministration($administrationId, $sourceOrderId);
        $invoice = $order === null ? null : $this->invoices->findLockedForAdministration($administrationId, $invoiceId);
        if ($invoice !== null && ! $invoice->sourceOrderId()?->equals($sourceOrderId)) {
            return [null, null];
        }

        return [$invoice, $order];
    }

    private function validateFacts(AdministrationId $administrationId, SalesInvoice $invoice, OrderId $orderId): array|SalesInvoiceWriteResult
    {
        $reservations = $this->progress->reservationsForSalesInvoice($administrationId, $invoice->id());
        if ($reservations === [] || count($reservations) !== count($invoice->lines())) {
            return SalesInvoiceWriteResult::ReservationStateInconsistent;
        }
        $requestId = $reservations[0]->draftRequestId;
        $request = $this->requests->find($administrationId, $requestId);
        if ($request === null || ! $request->orderId()->equals($orderId) || ! $request->salesInvoiceId()->equals($invoice->id())) {
            return SalesInvoiceWriteResult::ReservationStateInconsistent;
        }
        $byInvoiceLine = [];
        foreach ($reservations as $reservation) {
            if (! $reservation->draftRequestId->equals($requestId) || ! $reservation->orderId->equals($orderId) || ! $reservation->salesInvoiceId->equals($invoice->id())) {
                return SalesInvoiceWriteResult::ReservationStateInconsistent;
            }
            $line = $invoice->line($reservation->salesInvoiceLineId);
            if ($line === null || ! $line->quantity()->equals($reservation->quantity)) {
                return SalesInvoiceWriteResult::ReservationStateInconsistent;
            }
            $byInvoiceLine[$reservation->salesInvoiceLineId->toString()] = true;
        }
        foreach ($invoice->lines() as $line) {
            if (! isset($byInvoiceLine[$line->id()->toString()])) {
                return SalesInvoiceWriteResult::ReservationStateInconsistent;
            }
        }
        $active = array_values(array_filter(
            $this->progress->activeReservationsForOrder($administrationId, $orderId),
            static fn (OrderInvoicingReservationView $reservation): bool => $reservation->salesInvoiceId->equals($invoice->id()),
        ));

        return [$reservations, $active, $this->progress->allocationsForSalesInvoice($administrationId, $invoice->id())];
    }

    private function allocationsMatchReservations(array $reservations, array $allocations): bool
    {
        if (count($reservations) !== count($allocations)) {
            return false;
        }
        $byReservation = [];
        foreach ($allocations as $allocation) {
            $byReservation[$allocation->reservationId->toString()] = $allocation;
        }
        foreach ($reservations as $reservation) {
            $allocation = $byReservation[$reservation->id->toString()] ?? null;
            if ($allocation === null || ! $allocation->orderId->equals($reservation->orderId) || ! $allocation->orderLineId->equals($reservation->orderLineId)
                || ! $allocation->salesInvoiceId->equals($reservation->salesInvoiceId) || ! $allocation->salesInvoiceLineId->equals($reservation->salesInvoiceLineId)
                || ! $allocation->quantity->equals($reservation->quantity)) {
                return false;
            }
        }

        return true;
    }
}

final class OrderInvoiceLifecycleFailure extends RuntimeException
{
    public function __construct(public readonly SalesInvoiceWriteResult $result)
    {
        parent::__construct($result->name);
    }
}
