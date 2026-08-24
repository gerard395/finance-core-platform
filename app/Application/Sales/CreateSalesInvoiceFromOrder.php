<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Sales\Entities\OrderInvoiceDraftRequest;
use App\Domain\Sales\Entities\OrderInvoiceReservation;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesTaxSnapshot;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final readonly class CreateSalesInvoiceFromOrder
{
    public function __construct(
        private TransactionManager $transactions,
        private OrderInvoicingSource $orders,
        private OrderInvoiceDraftRequestReader $requests,
        private OrderInvoicingProgressReader $progress,
        private SalesInvoiceAddressResolver $addresses,
        private SalesTaxCodeResolver $taxCodes,
        private SalesInvoiceReadinessChecker $readiness,
        private SalesNumberAllocator $numbers,
        private OrderInvoiceDraftIdentityGenerator $identities,
        private SalesInvoiceCreator $invoices,
        private OrderInvoicingFactStore $facts,
        private SalesInvoiceReadRepository $invoiceReader,
    ) {}

    /** @param list<CreateSalesInvoiceFromOrderLineInput> $lines */
    public function execute(AdministrationId $administrationId, OrderId $orderId, OrderInvoiceDraftRequestId $requestId, AddressId $invoiceAddressId, DateTimeImmutable $invoiceDate, DateTimeImmutable $dueDate, array $lines): CreateSalesInvoiceFromOrderResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $orderId, $requestId, $invoiceAddressId, $invoiceDate, $dueDate, $lines): CreateSalesInvoiceFromOrderResult {
                $order = $this->orders->findLockedForAdministration($administrationId, $orderId);
                if ($order === null) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::NotFound);
                }
                $existing = $this->requests->find($administrationId, $requestId);
                if ($existing !== null) {
                    return $this->replay($administrationId, $orderId, $existing, $invoiceAddressId, $invoiceDate, $dueDate, $lines);
                }
                if (! in_array($order->status(), [OrderStatus::Confirmed, OrderStatus::PartiallyInvoiced], true)) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::InvalidOrderState);
                }
                if ($lines === []) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::NothingToInvoice);
                }
                $current = $this->progress->progress($administrationId, $orderId);
                if ($current === null) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::NotFound);
                }
                $progressByLine = [];
                foreach ($current->lines() as $line) {
                    $progressByLine[$line->orderLineId()->toString()] = $line;
                }
                $selected = [];
                foreach ($lines as $input) {
                    $key = $input->orderLineId()->toString();
                    if (isset($selected[$key])) {
                        return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::RequestConflict);
                    }
                    $sourceLine = $order->line($input->orderLineId());
                    $lineProgress = $progressByLine[$key] ?? null;
                    if ($sourceLine === null || $lineProgress === null) {
                        return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::NotFound);
                    }
                    if ($lineProgress->available()->isLessThan($input->quantity())) {
                        return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::QuantityExceedsRemaining);
                    }
                    $resolution = $this->taxCodes->resolve($administrationId, $input->taxCodeId());
                    $taxFailure = $this->taxFailure($resolution->status());
                    if ($taxFailure !== null) {
                        return CreateSalesInvoiceFromOrderResult::forStatus($taxFailure);
                    }
                    $taxCode = $resolution->taxCode();
                    if ($taxCode === null) {
                        return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::TaxCodeNotFound);
                    }
                    $selected[$key] = [$input, $sourceLine, SalesTaxSnapshot::fromTaxCode($taxCode)];
                }
                $customer = $order->customerSnapshot();
                if ($customer === null) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::InvalidOrderState);
                }
                $address = $this->addresses->resolve($administrationId, $customer->relationId(), $invoiceAddressId);
                if ($address === null) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::MissingInvoiceAddress);
                }
                $numberAllocation = $this->numbers->next($administrationId, SalesNumberType::SalesInvoice);
                if ($numberAllocation->status() === SalesNumberAllocationStatus::SequenceMissing) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::SequenceMissing);
                }
                if ($numberAllocation->status() === SalesNumberAllocationStatus::SequenceInactive) {
                    return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::SequenceInactive);
                }
                $number = $numberAllocation->number();
                if (! $number instanceof SalesInvoiceNumber) {
                    throw new OrderInvoiceDraftCreationFailed(CreateSalesInvoiceFromOrderStatus::PersistenceConflict);
                }
                $invoiceId = $this->identities->salesInvoiceId();
                $invoice = new SalesInvoice($invoiceId, $number, $administrationId, $order->customerId(), $order->currency(), $invoiceDate, $dueDate, $orderId, SalesInvoiceStatus::Draft, $customer, $address);
                $reservations = [];
                foreach ($selected as [$input, $sourceLine, $taxSnapshot]) {
                    $invoiceLineId = $this->identities->salesInvoiceLineId();
                    try {
                        $invoiceLine = new SalesInvoiceLine($invoiceLineId, $sourceLine->description(), $input->quantity(), $sourceLine->unitPrice(), $taxSnapshot);
                    } catch (InvalidArgumentException) {
                        throw new OrderInvoiceDraftCreationFailed(CreateSalesInvoiceFromOrderStatus::TaxCalculationFailed);
                    }
                    $invoice->addLine($invoiceLine);
                    $reservations[] = new OrderInvoiceReservation($this->identities->reservationId(), $administrationId, $requestId, $orderId, $input->orderLineId(), $invoiceId, $invoiceLineId, $input->quantity());
                }
                if ($this->readiness->check($invoice)->status() !== SalesInvoiceReadinessStatus::Ready) {
                    throw new OrderInvoiceDraftCreationFailed(CreateSalesInvoiceFromOrderStatus::TaxCalculationFailed);
                }
                if ($this->invoices->create($administrationId, $invoice) !== SalesInvoiceWriteResult::Success) {
                    throw new OrderInvoiceDraftCreationFailed(CreateSalesInvoiceFromOrderStatus::PersistenceConflict);
                }
                $requestResult = $this->facts->appendDraftRequest(new OrderInvoiceDraftRequest($requestId, $administrationId, $orderId, $invoiceId));
                if ($requestResult !== OrderInvoicingFactAppendResult::Appended) {
                    throw new OrderInvoiceDraftCreationFailed($requestResult === OrderInvoicingFactAppendResult::AlreadyExists ? CreateSalesInvoiceFromOrderStatus::RequestConflict : CreateSalesInvoiceFromOrderStatus::PersistenceConflict);
                }
                foreach ($reservations as $reservation) {
                    $result = $this->facts->appendReservation($reservation);
                    if ($result !== OrderInvoicingFactAppendResult::Appended) {
                        throw new OrderInvoiceDraftCreationFailed($result === OrderInvoicingFactAppendResult::QuantityExceedsAvailable ? CreateSalesInvoiceFromOrderStatus::QuantityExceedsRemaining : CreateSalesInvoiceFromOrderStatus::PersistenceConflict);
                    }
                }

                return CreateSalesInvoiceFromOrderResult::success($invoiceId);
            });
        } catch (OrderInvoiceDraftCreationFailed $failure) {
            return CreateSalesInvoiceFromOrderResult::forStatus($failure->status);
        }
    }

    /** @param list<CreateSalesInvoiceFromOrderLineInput> $lines */
    private function replay(AdministrationId $administrationId, OrderId $orderId, OrderInvoiceDraftRequest $request, AddressId $addressId, DateTimeImmutable $invoiceDate, DateTimeImmutable $dueDate, array $lines): CreateSalesInvoiceFromOrderResult
    {
        if (! $request->orderId()->equals($orderId)) {
            return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::RequestConflict);
        }
        $invoice = $this->invoiceReader->findForAdministration($administrationId, $request->salesInvoiceId());
        $reservations = $this->progress->reservationsForDraftRequest($administrationId, $request->id());
        if ($invoice === null || ! $invoice->sourceOrderId()?->equals($orderId) || ! $invoice->invoiceAddressSnapshot()?->addressId()->equals($addressId)
            || $invoice->invoiceDate() != $invoiceDate || $invoice->dueDate() != $dueDate || count($reservations) !== count($lines)) {
            return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::RequestConflict);
        }
        $byOrderLine = [];
        foreach ($reservations as $reservation) {
            $byOrderLine[$reservation->orderLineId->toString()] = $reservation;
        }
        foreach ($lines as $line) {
            $reservation = $byOrderLine[$line->orderLineId()->toString()] ?? null;
            $invoiceLine = $reservation === null ? null : $invoice->line($reservation->salesInvoiceLineId);
            if ($reservation === null || ! $reservation->quantity->equals($line->quantity()) || ! $invoiceLine?->taxSnapshot()?->taxCodeId()->equals($line->taxCodeId())) {
                return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::RequestConflict);
            }
        }

        return CreateSalesInvoiceFromOrderResult::forStatus(CreateSalesInvoiceFromOrderStatus::AlreadyCreated, $invoice->id());
    }

    private function taxFailure(SalesTaxCodeResolutionStatus $status): ?CreateSalesInvoiceFromOrderStatus
    {
        return match ($status) {
            SalesTaxCodeResolutionStatus::Success => null,
            SalesTaxCodeResolutionStatus::NotFound => CreateSalesInvoiceFromOrderStatus::TaxCodeNotFound,
            SalesTaxCodeResolutionStatus::Inactive => CreateSalesInvoiceFromOrderStatus::TaxCodeInactive,
            SalesTaxCodeResolutionStatus::WrongDirection => CreateSalesInvoiceFromOrderStatus::TaxCodeWrongDirection,
        };
    }
}

final class OrderInvoiceDraftCreationFailed extends RuntimeException
{
    public function __construct(public readonly CreateSalesInvoiceFromOrderStatus $status)
    {
        parent::__construct($status->name);
    }
}
