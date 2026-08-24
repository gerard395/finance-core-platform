<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final class OpenItem
{
    /** @var array<string, OpenItemSettlement> */
    private array $settlements = [];

    /** @var array<string, OpenItemMatch> */
    private array $matches = [];

    private readonly OpenItemSide $side;

    public function __construct(
        private readonly OpenItemId $id,
        private readonly AdministrationId $administrationId,
        private readonly RelationId $relationId,
        private readonly JournalEntryId $journalEntryId,
        private readonly OpenItemType $type,
        private readonly Money $originalAmount,
        private readonly PostingDate $openedOn,
        ?OpenItemSide $side = null,
    ) {
        if (! $originalAmount->isPositive()) {
            throw new DomainException('Original amount must be positive.');
        }

        $this->side = $side ?? match ($type) {
            OpenItemType::Receivable => OpenItemSide::Debit,
            OpenItemType::Payable => OpenItemSide::Credit,
        };
    }

    /** @param list<OpenItemSettlement> $settlements @param list<OpenItemMatch> $matches */
    public static function reconstitute(
        OpenItemId $id,
        AdministrationId $administrationId,
        RelationId $relationId,
        JournalEntryId $journalEntryId,
        OpenItemType $type,
        Money $originalAmount,
        PostingDate $openedOn,
        array $settlements,
        ?OpenItemSide $side = null,
        array $matches = [],
    ): self {
        $item = new self($id, $administrationId, $relationId, $journalEntryId, $type, $originalAmount, $openedOn, $side);
        $indexed = [];
        $reversed = [];

        foreach (self::ordered($settlements) as $settlement) {
            $key = $settlement->id()->toString();

            if (isset($indexed[$key])) {
                throw new DomainException('Settlement ID must be unique within an open item.');
            }

            if (! $originalAmount->currency()->equals($settlement->amount()->currency())) {
                throw new DomainException('Settlement currency must match the open item currency.');
            }

            if ($settlement->effectiveDate()->value() < $openedOn->value()) {
                throw new DomainException('Date cannot be before the open item opening date.');
            }

            if ($settlement->type() === OpenItemSettlementType::Reversal) {
                $reversedId = $settlement->reversedSettlementId();
                $applied = $reversedId === null ? null : ($indexed[$reversedId->toString()] ?? null);

                if ($applied === null || $applied->type() !== OpenItemSettlementType::Applied) {
                    throw new DomainException('A reversal must reference an existing applied settlement.');
                }

                if (isset($reversed[$reversedId->toString()])) {
                    throw new DomainException('An applied settlement can only be reversed once.');
                }

                if (! $settlement->amount()->equals($applied->amount())) {
                    throw new DomainException('A reversal amount must equal the applied settlement amount.');
                }

                $reversed[$reversedId->toString()] = true;
            }

            $indexed[$key] = $settlement;
        }

        foreach ($matches as $match) {
            if (! $match->administrationId()->equals($administrationId) || ! $match->involves($id)) {
                throw new DomainException('Open item match must belong to this OpenItem and Administration.');
            }
            if (! $originalAmount->currency()->equals($match->amount()->currency())) {
                throw new DomainException('Open item match currency must match the open item currency.');
            }
            if ($match->occurredOn()->value() < $openedOn->value()) {
                throw new DomainException('Open item match date cannot precede the opening date.');
            }
            if (isset($item->matches[$match->id()->toString()])) {
                throw new DomainException('Open item match identity must be unique.');
            }
            $item->matches[$match->id()->toString()] = $match;
        }

        $item->calculateOpenAmount(array_values($indexed), array_values($item->matches));
        $item->settlements = $indexed;

        return $item;
    }

    public function id(): OpenItemId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function relationId(): RelationId
    {
        return $this->relationId;
    }

    public function journalEntryId(): JournalEntryId
    {
        return $this->journalEntryId;
    }

    public function type(): OpenItemType
    {
        return $this->type;
    }

    public function side(): OpenItemSide
    {
        return $this->side;
    }

    public function originalAmount(): Money
    {
        return $this->originalAmount;
    }

    public function openedOn(): PostingDate
    {
        return $this->openedOn;
    }

    /** @return list<OpenItemSettlement> */
    public function settlements(): array
    {
        return self::ordered(array_values($this->settlements));
    }

    /** @return list<OpenItemMatch> */
    public function matches(): array
    {
        return self::orderedMatches(array_values($this->matches));
    }

    public function settlement(OpenItemSettlementId $id): ?OpenItemSettlement
    {
        return $this->settlements[$id->toString()] ?? null;
    }

    public function hasSettlement(OpenItemSettlementId $id): bool
    {
        return isset($this->settlements[$id->toString()]);
    }

    public function openAmount(): Money
    {
        return $this->calculateOpenAmount($this->settlements(), $this->matches());
    }

    public function status(): OpenItemStatus
    {
        return $this->statusFor($this->openAmount());
    }

    public function openAmountAt(PostingDate $date): Money
    {
        $this->assertOnOrAfterOpenedOn($date);

        return $this->calculateOpenAmount(array_values(array_filter(
            $this->settlements(),
            static fn (OpenItemSettlement $settlement): bool => $settlement->effectiveDate()->value() <= $date->value(),
        )), array_values(array_filter(
            $this->matches(),
            static fn (OpenItemMatch $match): bool => $match->occurredOn()->value() <= $date->value(),
        )));
    }

    public function statusAt(PostingDate $date): OpenItemStatus
    {
        return $this->statusFor($this->openAmountAt($date));
    }

    public function isOpen(): bool
    {
        return $this->status() === OpenItemStatus::Open;
    }

    public function isPartiallySettled(): bool
    {
        return $this->status() === OpenItemStatus::PartiallySettled;
    }

    public function isClosed(): bool
    {
        return $this->status() === OpenItemStatus::Closed;
    }

    public function applySettlement(
        OpenItemSettlementId $id,
        PostingDate $effectiveDate,
        Money $amount,
        JournalEntryId $sourceJournalEntryId,
    ): void {
        $this->assertNewSettlement($id, $effectiveDate, $amount);

        $this->appendWhenHistoryRemainsValid(new OpenItemSettlement(
            $id,
            $effectiveDate,
            $amount,
            $sourceJournalEntryId,
            OpenItemSettlementType::Applied,
            null,
        ));
    }

    public function reverseSettlement(
        OpenItemSettlementId $id,
        PostingDate $effectiveDate,
        OpenItemSettlementId $settlementId,
        JournalEntryId $sourceJournalEntryId,
    ): void {
        if ($this->hasSettlement($id)) {
            throw new DomainException('Settlement ID must be unique within an open item.');
        }

        $this->assertOnOrAfterOpenedOn($effectiveDate);
        $applied = $this->settlement($settlementId);

        if ($applied === null || $applied->type() !== OpenItemSettlementType::Applied) {
            throw new DomainException('A reversal must reference an existing applied settlement.');
        }

        foreach ($this->settlements as $settlement) {
            if ($settlement->type() === OpenItemSettlementType::Reversal
                && $settlement->reversedSettlementId()?->equals($settlementId)) {
                throw new DomainException('An applied settlement can only be reversed once.');
            }
        }

        $this->appendWhenHistoryRemainsValid(new OpenItemSettlement(
            $id,
            $effectiveDate,
            $applied->amount(),
            $sourceJournalEntryId,
            OpenItemSettlementType::Reversal,
            $settlementId,
        ));
    }

    private function assertNewSettlement(OpenItemSettlementId $id, PostingDate $effectiveDate, Money $amount): void
    {
        if ($this->hasSettlement($id)) {
            throw new DomainException('Settlement ID must be unique within an open item.');
        }

        $this->assertOnOrAfterOpenedOn($effectiveDate);

        if (! $amount->isPositive()) {
            throw new DomainException('Settlement amount must be positive.');
        }

        if (! $this->originalAmount->currency()->equals($amount->currency())) {
            throw new DomainException('Settlement currency must match the open item currency.');
        }
    }

    private function assertOnOrAfterOpenedOn(PostingDate $date): void
    {
        if ($date->value() < $this->openedOn->value()) {
            throw new DomainException('Date cannot be before the open item opening date.');
        }
    }

    private function appendWhenHistoryRemainsValid(OpenItemSettlement $candidate): void
    {
        $history = self::ordered([...$this->settlements(), $candidate]);
        $this->calculateOpenAmount($history, $this->matches());
        $this->settlements[$candidate->id()->toString()] = $candidate;
    }

    /** @param list<OpenItemSettlement> $settlements @param list<OpenItemMatch> $matches */
    private function calculateOpenAmount(array $settlements, array $matches): Money
    {
        $openAmount = $this->originalAmount;

        foreach ($settlements as $settlement) {
            $openAmount = $settlement->type() === OpenItemSettlementType::Applied
                ? $openAmount->subtract($settlement->amount())
                : $openAmount->add($settlement->amount());

            if ($openAmount->isNegative()) {
                throw new DomainException('Settlement history cannot reduce the open amount below zero.');
            }

            if ($openAmount->subtract($this->originalAmount)->isPositive()) {
                throw new DomainException('Settlement history cannot increase the open amount above the original amount.');
            }
        }

        foreach ($matches as $match) {
            $openAmount = $openAmount->subtract($match->amount());
            if ($openAmount->isNegative()) {
                throw new DomainException('Open item matches cannot reduce the open amount below zero.');
            }
        }

        return $openAmount;
    }

    private function statusFor(Money $openAmount): OpenItemStatus
    {
        if ($openAmount->isZero()) {
            return OpenItemStatus::Closed;
        }

        return $openAmount->equals($this->originalAmount)
            ? OpenItemStatus::Open
            : OpenItemStatus::PartiallySettled;
    }

    /**
     * @param  list<OpenItemSettlement>  $settlements
     * @return list<OpenItemSettlement>
     */
    private static function ordered(array $settlements): array
    {
        usort($settlements, static function (OpenItemSettlement $left, OpenItemSettlement $right): int {
            $dateOrder = $left->effectiveDate()->value() <=> $right->effectiveDate()->value();

            return $dateOrder !== 0 ? $dateOrder : strcmp($left->id()->toString(), $right->id()->toString());
        });

        return $settlements;
    }

    /** @param list<OpenItemMatch> $matches @return list<OpenItemMatch> */
    private static function orderedMatches(array $matches): array
    {
        usort($matches, static function (OpenItemMatch $left, OpenItemMatch $right): int {
            $dateOrder = $left->occurredOn()->value() <=> $right->occurredOn()->value();

            return $dateOrder !== 0 ? $dateOrder : strcmp($left->id()->toString(), $right->id()->toString());
        });

        return $matches;
    }
}
