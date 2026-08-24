<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Quotation
{
    /** @var array<string, QuotationLine> */
    private array $lines = [];

    public function __construct(
        private readonly QuotationId $id,
        private readonly QuotationNumber $number,
        private readonly AdministrationId $administrationId,
        private readonly CustomerId $customerId,
        private readonly Currency $currency,
        private QuotationStatus $status,
        private DateTimeImmutable $quotationDate,
        private ?DateTimeImmutable $expiryDate,
        private readonly ?SalesCustomerSnapshot $customerSnapshot = null,
    ) {
        self::assertDates($quotationDate, $expiryDate);
        self::assertCustomerSnapshot($customerId, $customerSnapshot);
    }

    /** @param list<QuotationLine> $lines */
    public static function reconstitute(
        QuotationId $id,
        QuotationNumber $number,
        AdministrationId $administrationId,
        CustomerId $customerId,
        Currency $currency,
        QuotationStatus $status,
        DateTimeImmutable $quotationDate,
        ?DateTimeImmutable $expiryDate,
        array $lines,
        ?SalesCustomerSnapshot $customerSnapshot = null,
    ): self {
        $quotation = new self($id, $number, $administrationId, $customerId, $currency, $status, $quotationDate, $expiryDate, $customerSnapshot);
        $quotation->restoreLines($lines);

        if (in_array($status, [QuotationStatus::Sent, QuotationStatus::Accepted, QuotationStatus::Rejected], true) && $lines === []) {
            throw new DomainException('A sent, accepted or rejected quotation must contain at least one line.');
        }

        return $quotation;
    }

    public function id(): QuotationId
    {
        return $this->id;
    }

    public function number(): QuotationNumber
    {
        return $this->number;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function customerSnapshot(): ?SalesCustomerSnapshot
    {
        return $this->customerSnapshot;
    }

    private static function assertCustomerSnapshot(CustomerId $customerId, ?SalesCustomerSnapshot $snapshot): void
    {
        if ($snapshot !== null && ! $snapshot->customerId()->equals($customerId)) {
            throw new DomainException('Quotation customer snapshot must match CustomerId.');
        }
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
        return array_values($this->lines);
    }

    public function hasLine(QuotationLineId $lineId): bool
    {
        return isset($this->lines[$lineId->toString()]);
    }

    public function line(QuotationLineId $lineId): ?QuotationLine
    {
        return $this->lines[$lineId->toString()] ?? null;
    }

    public function addLine(QuotationLine $line): void
    {
        $this->assertDraftForLineChanges();
        $this->assertLineCurrency($line);
        $key = $line->id()->toString();

        if (isset($this->lines[$key])) {
            throw new DomainException('Quotation already contains a line with this identity.');
        }

        $this->lines[$key] = $line;
    }

    public function updateLine(QuotationLine $line): void
    {
        $this->assertDraftForLineChanges();
        $this->assertLineCurrency($line);
        $key = $line->id()->toString();

        if (! isset($this->lines[$key])) {
            throw new DomainException('Quotation line to update does not exist.');
        }

        $this->lines[$key] = $line;
    }

    public function removeLine(QuotationLineId $lineId): void
    {
        $this->assertDraftForLineChanges();
        unset($this->lines[$lineId->toString()]);
    }

    public function changeDates(DateTimeImmutable $quotationDate, ?DateTimeImmutable $expiryDate): void
    {
        $this->assertDraftForLineChanges();
        self::assertDates($quotationDate, $expiryDate);
        $this->quotationDate = $quotationDate;
        $this->expiryDate = $expiryDate;
    }

    public function total(): Money
    {
        $total = Money::zero($this->currency);
        foreach ($this->lines as $line) {
            $total = $total->add($line->lineTotal());
        }

        return $total;
    }

    public function send(): void
    {
        if ($this->status === QuotationStatus::Draft && $this->lines === []) {
            throw new DomainException('Quotation must contain at least one line before it can be sent.');
        }

        $this->transition(QuotationStatus::Draft, QuotationStatus::Sent);
    }

    public function accept(): void
    {
        $this->transition(QuotationStatus::Sent, QuotationStatus::Accepted);
    }

    public function reject(): void
    {
        $this->transition(QuotationStatus::Sent, QuotationStatus::Rejected);
    }

    public function expire(): void
    {
        if ($this->status === QuotationStatus::Expired) {
            return;
        }

        if (! in_array($this->status, [QuotationStatus::Draft, QuotationStatus::Sent], true)) {
            throw new DomainException('Quotation cannot expire from its current status.');
        }

        $this->status = QuotationStatus::Expired;
    }

    private function transition(QuotationStatus $from, QuotationStatus $to): void
    {
        if ($this->status === $to) {
            return;
        }

        if ($this->status !== $from) {
            throw new DomainException("Quotation cannot transition from {$this->status->value} to {$to->value}.");
        }

        $this->status = $to;
    }

    private function assertDraftForLineChanges(): void
    {
        if ($this->status !== QuotationStatus::Draft) {
            throw new DomainException('Quotation lines can only be changed while the quotation is in draft.');
        }
    }

    /** @param list<QuotationLine> $lines */
    private function restoreLines(array $lines): void
    {
        foreach ($lines as $line) {
            $this->assertLineCurrency($line);
            $key = $line->id()->toString();
            if (isset($this->lines[$key])) {
                throw new DomainException('Quotation already contains a line with this identity.');
            }
            $this->lines[$key] = $line;
        }
    }

    private function assertLineCurrency(QuotationLine $line): void
    {
        if (! $line->unitPrice()->currency()->equals($this->currency)) {
            throw new DomainException('Quotation line currency must match document currency.');
        }
    }

    private static function assertDates(DateTimeImmutable $quotationDate, ?DateTimeImmutable $expiryDate): void
    {
        if ($expiryDate !== null && $expiryDate < $quotationDate) {
            throw new InvalidArgumentException('Expiry date cannot precede quotation date.');
        }
    }
}
