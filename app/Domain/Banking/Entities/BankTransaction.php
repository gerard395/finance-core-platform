<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final class BankTransaction
{
    public function __construct(
        private readonly BankTransactionId $id,
        private readonly BankAccountId $bankAccountId,
        private readonly AdministrationId $administrationId,
        private readonly DateTimeImmutable $bookingDate,
        private readonly DateTimeImmutable $valueDate,
        private readonly Money $amount,
        private readonly BankTransactionReference $reference,
        private readonly TransactionDescription $description,
        private BankTransactionStatus $status,
    ) {}

    public function id(): BankTransactionId
    {
        return $this->id;
    }

    public function bankAccountId(): BankAccountId
    {
        return $this->bankAccountId;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function bookingDate(): DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function valueDate(): DateTimeImmutable
    {
        return $this->valueDate;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function reference(): BankTransactionReference
    {
        return $this->reference;
    }

    public function description(): TransactionDescription
    {
        return $this->description;
    }

    public function status(): BankTransactionStatus
    {
        return $this->status;
    }

    public function match(): void
    {
        $this->transitionTo(BankTransactionStatus::Matched, [BankTransactionStatus::Imported]);
    }

    public function post(): void
    {
        $this->transitionTo(BankTransactionStatus::Posted, [BankTransactionStatus::Matched]);
    }

    /** @param list<BankTransactionStatus> $allowedFrom */
    private function transitionTo(BankTransactionStatus $target, array $allowedFrom): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! in_array($this->status, $allowedFrom, true)) {
            throw new DomainException("Bank transaction cannot transition from {$this->status->value} to {$target->value}.");
        }

        $this->status = $target;
    }
}
