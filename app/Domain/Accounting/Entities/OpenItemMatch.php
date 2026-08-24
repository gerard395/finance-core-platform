<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemMatchId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class OpenItemMatch
{
    public function __construct(
        private OpenItemMatchId $id,
        private AdministrationId $administrationId,
        private OpenItemId $debitOpenItemId,
        private OpenItemId $creditOpenItemId,
        private Money $amount,
        private PostingDate $occurredOn,
        private JournalEntryId $sourceJournalEntryId,
    ) {
        if ($debitOpenItemId->equals($creditOpenItemId)) {
            throw new DomainException('An open item cannot be matched with itself.');
        }
        if (! $amount->isPositive()) {
            throw new DomainException('Open item match amount must be positive.');
        }
    }

    public function id(): OpenItemMatchId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function debitOpenItemId(): OpenItemId
    {
        return $this->debitOpenItemId;
    }

    public function creditOpenItemId(): OpenItemId
    {
        return $this->creditOpenItemId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function occurredOn(): PostingDate
    {
        return $this->occurredOn;
    }

    public function sourceJournalEntryId(): JournalEntryId
    {
        return $this->sourceJournalEntryId;
    }

    public function involves(OpenItemId $openItemId): bool
    {
        return $this->debitOpenItemId->equals($openItemId) || $this->creditOpenItemId->equals($openItemId);
    }
}
