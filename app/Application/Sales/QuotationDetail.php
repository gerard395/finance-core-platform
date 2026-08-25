<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class QuotationDetail
{
    /** @param list<QuotationLine> $lines */
    public function __construct(private QuotationId $id, private QuotationNumber $number, private SalesCustomerSnapshot $customer, private ?SalesAddressSnapshot $documentAddress, private Currency $currency, private QuotationStatus $status, private DateTimeImmutable $quotationDate, private ?DateTimeImmutable $expiryDate, private array $lines, private Money $total) {}

    public static function fromQuotation(Quotation $quotation): self
    {
        $snapshot = $quotation->customerSnapshot();
        if ($snapshot === null) {
            throw new \DomainException('Persistent Quotation requires a Customer snapshot.');
        }

        return new self($quotation->id(), $quotation->number(), $snapshot, $quotation->documentAddressSnapshot(), $quotation->currency(), $quotation->status(), $quotation->quotationDate(), $quotation->expiryDate(), $quotation->lines(), $quotation->total());
    }

    public function id(): QuotationId
    {
        return $this->id;
    }

    public function number(): QuotationNumber
    {
        return $this->number;
    }

    public function customer(): SalesCustomerSnapshot
    {
        return $this->customer;
    }

    public function documentAddress(): ?SalesAddressSnapshot
    {
        return $this->documentAddress;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function status(): QuotationStatus
    {
        return $this->status;
    }

    public function quotationDate(): DateTimeImmutable
    {
        return $this->quotationDate;
    }

    public function expiryDate(): ?DateTimeImmutable
    {
        return $this->expiryDate;
    }

    /** @return list<QuotationLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function total(): Money
    {
        return $this->total;
    }
}
