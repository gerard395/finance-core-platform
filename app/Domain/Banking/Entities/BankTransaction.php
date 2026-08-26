<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final class BankTransaction
{
    public function __construct(private readonly BankTransactionId $id, private AdministrationBankAccountId $bankAccountId, private readonly AdministrationId $administrationId, private TransactionDate $transactionDate, private Money $amount, private BankTransactionReference $reference, private TransactionDescription $description, private Payment $payment, private BankTransactionStatus $status, private readonly UserId $createdBy, private readonly DateTimeImmutable $createdAt, private ?UserId $finalizedBy = null, private ?DateTimeImmutable $finalizedAt = null)
    {
        $this->assertCoherent();
        if ($status === BankTransactionStatus::Finalized && ($finalizedBy === null || $finalizedAt === null)) {
            throw new DomainException('Finalized audit facts are required.');
        }
    }

    public function id(): BankTransactionId
    {
        return $this->id;
    }

    public function bankAccountId(): AdministrationBankAccountId
    {
        return $this->bankAccountId;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function transactionDate(): TransactionDate
    {
        return $this->transactionDate;
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

    public function payment(): Payment
    {
        return $this->payment;
    }

    public function status(): BankTransactionStatus
    {
        return $this->status;
    }

    public function createdBy(): UserId
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function finalizedBy(): ?UserId
    {
        return $this->finalizedBy;
    }

    public function finalizedAt(): ?DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    public function updateDraft(AdministrationBankAccountId $bankAccountId, TransactionDate $date, Money $amount, BankTransactionReference $reference, TransactionDescription $description, Payment $payment): void
    {
        $this->assertDraft();
        $this->bankAccountId = $bankAccountId;
        $this->transactionDate = $date;
        $this->amount = $amount;
        $this->reference = $reference;
        $this->description = $description;
        $this->payment = $payment;
        $this->assertCoherent();
    }

    /** @param list<PaymentAllocation> $allocations */
    public function replaceAllocations(array $allocations): void
    {
        $this->assertDraft();
        $this->payment->replaceAllocations($allocations);
    }

    /** @param list<PaymentAllocation> $allocations */
    public function finalize(UserId $actor, DateTimeImmutable $at, array $allocations): void
    {
        if ($this->status === BankTransactionStatus::Finalized) {
            return;
        } $this->assertDraft();
        $this->payment->replaceAllocations($allocations);
        if ($allocations === [] || ! $this->payment->allocationTotal()->equals($this->amount->absolute())) {
            throw new DomainException('Finalization requires exact full allocation.');
        } foreach ($allocations as $allocation) {
            if (! $allocation->isFinalized()) {
                throw new DomainException('Finalized allocation snapshots are required.');
            }
        } $this->status = BankTransactionStatus::Finalized;
        $this->finalizedBy = $actor;
        $this->finalizedAt = $at;
    }

    public function cancel(): void
    {
        $this->assertDraft();
        $this->status = BankTransactionStatus::Cancelled;
    }

    private function assertDraft(): void
    {
        if ($this->status !== BankTransactionStatus::Draft) {
            throw new DomainException('Only Draft bank transactions are mutable.');
        }
    }

    private function assertCoherent(): void
    {
        if ($this->amount->isZero() || $this->amount->currency()->code() !== 'EUR') {
            throw new DomainException('Bank transaction requires non-zero EUR Money.');
        } if (! $this->payment->amount()->equals($this->amount->absolute())) {
            throw new DomainException('Payment amount must equal absolute bank transaction amount.');
        } $expected = $this->amount->isPositive() ? PaymentType::CustomerReceipt : PaymentType::SupplierPayment;
        if ($this->payment->type() !== $expected) {
            throw new DomainException('Payment type must be derived from signed amount.');
        }
    }
}
