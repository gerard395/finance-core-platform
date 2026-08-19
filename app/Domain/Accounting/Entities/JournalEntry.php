<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DomainException;

final class JournalEntry
{
    /** @var array<string, JournalEntryLine> */
    private array $lines = [];

    public function __construct(
        private readonly JournalEntryId $id,
        private readonly AdministrationId $administrationId,
        private readonly JournalId $journalId,
        private readonly PostingDate $postingDate,
        private readonly JournalEntryReference $reference,
        private JournalEntryStatus $status,
    ) {}

    public function id(): JournalEntryId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
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

    /** @return list<JournalEntryLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function line(JournalEntryLineId $lineId): ?JournalEntryLine
    {
        return $this->lines[$lineId->toString()] ?? null;
    }

    public function hasLine(JournalEntryLineId $lineId): bool
    {
        return isset($this->lines[$lineId->toString()]);
    }

    public function addLine(JournalEntryLine $line): void
    {
        $this->assertDraft();

        if ($this->hasLine($line->id())) {
            throw new DomainException('A journal entry line with this identity already exists.');
        }

        $this->lines[$line->id()->toString()] = $line;
    }

    public function removeLine(JournalEntryLineId $lineId): void
    {
        $this->assertDraft();

        unset($this->lines[$lineId->toString()]);
    }

    public function post(): void
    {
        $this->status = JournalEntryStatus::Posted;
    }

    private function assertDraft(): void
    {
        if ($this->isPosted()) {
            throw new DomainException('A posted journal entry is immutable.');
        }
    }
}
