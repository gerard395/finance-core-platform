<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final class BankTransaction
{
    /** @var array<string, Payment> */
    private array $payments = [];

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

    /** @return list<Payment> */
    public function payments(): array
    {
        return array_values($this->payments);
    }

    public function payment(PaymentId $paymentId): ?Payment
    {
        return $this->payments[$paymentId->toString()] ?? null;
    }

    public function hasPayment(PaymentId $paymentId): bool
    {
        return isset($this->payments[$paymentId->toString()]);
    }

    public function addPayment(Payment $payment): void
    {
        $this->assertImportedForPaymentChanges();

        if (! $this->amount->currency()->equals($payment->amount()->currency())) {
            throw new DomainException('Payment currency must match the bank transaction currency.');
        }

        $key = $payment->id()->toString();

        if (isset($this->payments[$key])) {
            throw new DomainException('Bank transaction already contains a payment with this identity.');
        }

        $this->payments[$key] = $payment;
    }

    public function removePayment(PaymentId $paymentId): void
    {
        $this->assertImportedForPaymentChanges();
        unset($this->payments[$paymentId->toString()]);
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

    private function assertImportedForPaymentChanges(): void
    {
        if ($this->status !== BankTransactionStatus::Imported) {
            throw new DomainException('Payments can only be changed while the bank transaction is imported.');
        }
    }
}
