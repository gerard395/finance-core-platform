<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\PostingDate;

final class JournalEntry
{
    public function __construct(
        private readonly JournalEntryId $id,
        private readonly JournalId $journalId,
        private readonly PostingDate $postingDate,
        private readonly JournalEntryReference $reference,
        private JournalEntryStatus $status,
    ) {}

    public function id(): JournalEntryId
    {
        return $this->id;
    }

    public function journalId(): JournalId
    {
        return $this->journalId;
    }

    public function postingDate(): PostingDate
    {
        return $this->postingDate;
    }

    public function reference(): JournalEntryReference
    {
        return $this->reference;
    }

    public function status(): JournalEntryStatus
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::Posted;
    }

    public function post(): void
    {
        $this->status = JournalEntryStatus::Posted;
    }
}
