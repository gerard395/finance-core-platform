<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Entities;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class TaxPosting
{
    public function __construct(
        private TaxPostingId $id,
        private AdministrationId $administrationId,
        private TaxCodeId $taxCodeId,
        private TaxRate $taxRate,
        private Money $taxableBase,
        private Money $taxAmount,
        private TaxPostingDirection $direction,
        private TaxSourceDocumentType $sourceDocumentType,
        private TaxSourceDocumentId $sourceDocumentId,
        private TaxSourceLineId $sourceLineId,
        private PostingDate $postingDate,
        private JournalEntryId $journalEntryId,
        private JournalEntryLineId $baseJournalEntryLineId,
        private ?JournalEntryLineId $taxJournalEntryLineId,
        private ?TaxPostingId $reversedTaxPostingId = null,
    ) {
        if (! $this->taxableBase->currency()->equals($this->taxAmount->currency())) {
            throw new DomainException('Tax posting amounts must use the same currency.');
        }

        if ($this->taxableBase->isNegative()) {
            throw new DomainException('Taxable base cannot be negative.');
        }

        if ($this->taxAmount->isNegative()) {
            throw new DomainException('Tax amount cannot be negative.');
        }

        if ($this->taxAmount->isPositive() && $this->taxJournalEntryLineId === null) {
            throw new DomainException('A positive tax amount requires a tax journal entry line.');
        }

        if ($this->taxAmount->isZero() && $this->taxJournalEntryLineId !== null) {
            throw new DomainException('A zero tax amount cannot reference a tax journal entry line.');
        }
    }

    public function id(): TaxPostingId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function taxCodeId(): TaxCodeId
    {
        return $this->taxCodeId;
    }

    public function taxRate(): TaxRate
    {
        return $this->taxRate;
    }

    public function taxableBase(): Money
    {
        return $this->taxableBase;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function direction(): TaxPostingDirection
    {
        return $this->direction;
    }

    public function sourceDocumentType(): TaxSourceDocumentType
    {
        return $this->sourceDocumentType;
    }

    public function sourceDocumentId(): TaxSourceDocumentId
    {
        return $this->sourceDocumentId;
    }

    public function sourceLineId(): TaxSourceLineId
    {
        return $this->sourceLineId;
    }

    public function postingDate(): PostingDate
    {
        return $this->postingDate;
    }

    public function journalEntryId(): JournalEntryId
    {
        return $this->journalEntryId;
    }

    public function baseJournalEntryLineId(): JournalEntryLineId
    {
        return $this->baseJournalEntryLineId;
    }

    public function taxJournalEntryLineId(): ?JournalEntryLineId
    {
        return $this->taxJournalEntryLineId;
    }

    public function reversedTaxPostingId(): ?TaxPostingId
    {
        return $this->reversedTaxPostingId;
    }

    public function isReversal(): bool
    {
        return $this->reversedTaxPostingId !== null;
    }
}
