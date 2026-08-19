<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class OpenItemSettlement
{
    public function __construct(
        private OpenItemSettlementId $id,
        private PostingDate $effectiveDate,
        private Money $amount,
        private JournalEntryId $sourceJournalEntryId,
        private OpenItemSettlementType $type,
        private ?OpenItemSettlementId $reversedSettlementId,
    ) {
        if (! $amount->isPositive()) {
            throw new DomainException('Settlement amount must be positive.');
        }

        if ($type === OpenItemSettlementType::Applied && $reversedSettlementId !== null) {
            throw new DomainException('An applied settlement cannot reference a reversed settlement.');
        }

        if ($type === OpenItemSettlementType::Reversal && $reversedSettlementId === null) {
            throw new DomainException('A reversal must reference an applied settlement.');
        }
    }

    public function id(): OpenItemSettlementId
    {
        return $this->id;
    }

    public function effectiveDate(): PostingDate
    {
        return $this->effectiveDate;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function sourceJournalEntryId(): JournalEntryId
    {
        return $this->sourceJournalEntryId;
    }

    public function type(): OpenItemSettlementType
    {
        return $this->type;
    }

    public function reversedSettlementId(): ?OpenItemSettlementId
    {
        return $this->reversedSettlementId;
    }
}
