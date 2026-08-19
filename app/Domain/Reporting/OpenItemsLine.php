<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;

final readonly class OpenItemsLine
{
    public function __construct(
        private OpenItemId $openItemId,
        private RelationId $relationId,
        private JournalEntryId $journalEntryId,
        private PostingDate $openedOn,
        private Money $originalAmount,
        private Money $openAmount,
        private OpenItemStatus $status,
    ) {}

    public function openItemId(): OpenItemId
    {
        return $this->openItemId;
    }

    public function relationId(): RelationId
    {
        return $this->relationId;
    }

    public function journalEntryId(): JournalEntryId
    {
        return $this->journalEntryId;
    }

    public function openedOn(): PostingDate
    {
        return $this->openedOn;
    }

    public function originalAmount(): Money
    {
        return $this->originalAmount;
    }

    public function openAmount(): Money
    {
        return $this->openAmount;
    }

    public function status(): OpenItemStatus
    {
        return $this->status;
    }
}
