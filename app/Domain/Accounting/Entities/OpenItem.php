<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final class OpenItem
{
    public function __construct(
        private readonly OpenItemId $id,
        private readonly AdministrationId $administrationId,
        private readonly RelationId $relationId,
        private readonly JournalEntryId $journalEntryId,
        private readonly Money $originalAmount,
        private Money $openAmount,
        private OpenItemStatus $status,
    ) {
        self::assertNonNegative($originalAmount, 'Original amount');
        self::assertNonNegative($openAmount, 'Open amount');
        self::assertSameCurrency($originalAmount, $openAmount);

        if (self::compare($openAmount, $originalAmount) > 0) {
            throw new DomainException('Open amount cannot exceed original amount.');
        }

        if ($status === OpenItemStatus::Closed && ! $openAmount->isZero()) {
            throw new DomainException('A closed open item must have an open amount of zero.');
        }
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

    public function isOpen(): bool
    {
        return $this->status === OpenItemStatus::Open;
    }

    public function isPartiallySettled(): bool
    {
        return $this->status === OpenItemStatus::PartiallySettled;
    }

    public function isClosed(): bool
    {
        return $this->status === OpenItemStatus::Closed;
    }

    public function settle(Money $amount): void
    {
        self::assertSameCurrency($this->openAmount, $amount);
        self::assertNonNegative($amount, 'Settlement amount');

        if ($amount->isZero()) {
            return;
        }

        if ($this->isClosed()) {
            throw new DomainException('A closed open item cannot be settled.');
        }

        if (self::compare($amount, $this->openAmount) > 0) {
            throw new DomainException('Settlement amount cannot exceed open amount.');
        }

        $remaining = self::subtract(self::scaledAmount($this->openAmount), self::scaledAmount($amount));
        $this->openAmount = new Money(self::decimalAmount($remaining), $this->openAmount->currency());
        $this->status = OpenItemStatus::PartiallySettled;
    }

    public function close(): void
    {
        if ($this->isClosed()) {
            return;
        }

        if (! $this->openAmount->isZero()) {
            throw new DomainException('An open item can only be closed when its open amount is zero.');
        }

        $this->status = OpenItemStatus::Closed;
    }

    private static function assertNonNegative(Money $amount, string $field): void
    {
        if (str_starts_with($amount->amount(), '-')) {
            throw new DomainException($field.' cannot be negative.');
        }
    }

    private static function assertSameCurrency(Money $left, Money $right): void
    {
        if (! $left->currency()->equals($right->currency())) {
            throw new DomainException('Open item amounts must use the same currency.');
        }
    }

    private static function compare(Money $left, Money $right): int
    {
        return self::compareUnsigned(self::scaledAmount($left), self::scaledAmount($right));
    }

    private static function scaledAmount(Money $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount->amount(), 2), 2, '');

        return ltrim($whole.str_pad($fraction, 8, '0'), '0') ?: '0';
    }

    private static function compareUnsigned(string $left, string $right): int
    {
        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return $left <=> $right;
    }

    private static function subtract(string $left, string $right): string
    {
        $right = str_pad($right, strlen($left), '0', STR_PAD_LEFT);
        $borrow = 0;
        $result = '';

        for ($index = strlen($left) - 1; $index >= 0; $index--) {
            $digit = (int) $left[$index] - (int) $right[$index] - $borrow;
            $borrow = $digit < 0 ? 1 : 0;
            $result = ($digit < 0 ? $digit + 10 : $digit).$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function decimalAmount(string $scaledAmount): string
    {
        $digits = str_pad($scaledAmount, 9, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -8);
        $fraction = rtrim(substr($digits, -8), '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }
}
