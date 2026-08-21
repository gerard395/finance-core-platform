<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class SalesInvoiceDetail
{
    /** @param list<SalesInvoiceLine> $lines */
    public function __construct(private SalesInvoiceId $id, private SalesInvoiceNumber $number, private SalesCustomerSnapshot $customer, private SalesAddressSnapshot $invoiceAddress, private Currency $currency, private SalesInvoiceStatus $status, private DateTimeImmutable $invoiceDate, private DateTimeImmutable $dueDate, private ?OrderId $sourceOrderId, private array $lines, private Money $netTotal, private Money $taxTotal, private Money $grossTotal) {}

    public static function fromInvoice(SalesInvoice $invoice, SalesInvoiceReadiness $totals): self
    {
        $customer = $invoice->customerSnapshot();
        $address = $invoice->invoiceAddressSnapshot();
        if ($customer === null || $address === null || $totals->netTotal() === null || $totals->taxTotal() === null || $totals->grossTotal() === null) {
            throw new DomainException('Persistent SalesInvoice detail requires complete snapshots and exact totals.');
        }

        return new self($invoice->id(), $invoice->number(), $customer, $address, $invoice->currency(), $invoice->status(), $invoice->invoiceDate(), $invoice->dueDate(), $invoice->sourceOrderId(), $invoice->lines(), $totals->netTotal(), $totals->taxTotal(), $totals->grossTotal());
    }

    public function id(): SalesInvoiceId
    {
        return $this->id;
    }

    public function number(): SalesInvoiceNumber
    {
        return $this->number;
    }

    public function customer(): SalesCustomerSnapshot
    {
        return $this->customer;
    }

    public function invoiceAddress(): SalesAddressSnapshot
    {
        return $this->invoiceAddress;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function status(): SalesInvoiceStatus
    {
        return $this->status;
    }

    public function invoiceDate(): DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function sourceOrderId(): ?OrderId
    {
        return $this->sourceOrderId;
    }

    /** @return list<SalesInvoiceLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function netTotal(): Money
    {
        return $this->netTotal;
    }

    public function taxTotal(): Money
    {
        return $this->taxTotal;
    }

    public function grossTotal(): Money
    {
        return $this->grossTotal;
    }
}
