<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Fiscal\Entities\TaxPosting;

final readonly class VatOverviewLine
{
    public function __construct(private TaxPosting $posting) {}

    public function taxPosting(): TaxPosting
    {
        return $this->posting;
    }

    public function id(): mixed
    {
        return $this->posting->id();
    }

    public function type(): mixed
    {
        return $this->posting->type();
    }

    public function direction(): mixed
    {
        return $this->posting->direction();
    }

    public function taxCodeId(): mixed
    {
        return $this->posting->taxCodeId();
    }

    public function taxRate(): mixed
    {
        return $this->posting->taxRate();
    }

    public function postingDate(): mixed
    {
        return $this->posting->postingDate();
    }

    public function taxableBase(): mixed
    {
        return $this->posting->taxableBase();
    }

    public function taxAmount(): mixed
    {
        return $this->posting->taxAmount();
    }

    public function sourceDocumentType(): mixed
    {
        return $this->posting->sourceDocumentType();
    }

    public function sourceDocumentId(): mixed
    {
        return $this->posting->sourceDocumentId();
    }

    public function sourceLineId(): mixed
    {
        return $this->posting->sourceLineId();
    }

    public function journalEntryId(): mixed
    {
        return $this->posting->journalEntryId();
    }

    public function baseJournalEntryLineId(): mixed
    {
        return $this->posting->baseJournalEntryLineId();
    }

    public function taxJournalEntryLineId(): mixed
    {
        return $this->posting->taxJournalEntryLineId();
    }

    public function reversedTaxPostingId(): mixed
    {
        return $this->posting->reversedTaxPostingId();
    }
}
