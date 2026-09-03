<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionIntentType;
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
    public function __construct(private readonly BankTransactionId $id, private AdministrationBankAccountId $bankAccountId, private readonly AdministrationId $administrationId, private TransactionDate $transactionDate, private Money $amount, private BankTransactionReference $reference, private TransactionDescription $description, private Payment|OtherBankTransactionIntent $intent, private BankTransactionStatus $status, private readonly UserId $createdBy, private readonly DateTimeImmutable $createdAt, private ?UserId $finalizedBy = null, private ?DateTimeImmutable $finalizedAt = null, private ?UserId $postedBy = null, private ?DateTimeImmutable $postedAt = null)
    {
        $this->assertCoherent();
        if (in_array($status, [BankTransactionStatus::Finalized, BankTransactionStatus::Posted], true) && ($finalizedBy === null || $finalizedAt === null)) {
            throw new DomainException('Finalized audit facts are required.');
        }
        if ($status === BankTransactionStatus::Posted && ($postedBy === null || $postedAt === null)) {
            throw new DomainException('Posted audit facts are required.');
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
        if (! $this->intent instanceof Payment) {
            throw new DomainException('Bank transaction is not Payment-backed.');
        }

        return $this->intent;
    }

    public function paymentOrNull(): ?Payment
    {
        return $this->intent instanceof Payment ? $this->intent : null;
    }

    public function otherIntentOrNull(): ?OtherBankTransactionIntent
    {
        return $this->intent instanceof OtherBankTransactionIntent ? $this->intent : null;
    }

    public function intentType(): BankTransactionIntentType
    {
        return $this->intent instanceof Payment ? BankTransactionIntentType::Payment : BankTransactionIntentType::Other;
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

    public function postedBy(): ?UserId
    {
        return $this->postedBy;
    }

    public function postedAt(): ?DateTimeImmutable
    {
        return $this->postedAt;
    }

    public function markPosted(UserId $actor, DateTimeImmutable $at): void
    {
        if ($this->status !== BankTransactionStatus::Finalized) {
            throw new DomainException('Only Finalized bank transactions can be posted.');
        }
        $this->status = BankTransactionStatus::Posted;
        $this->postedBy = $actor;
        $this->postedAt = $at;
    }

    public function updateDraft(AdministrationBankAccountId $bankAccountId, TransactionDate $date, Money $amount, BankTransactionReference $reference, TransactionDescription $description, Payment $payment): void
    {
        $this->assertDraft();
        $this->bankAccountId = $bankAccountId;
        $this->transactionDate = $date;
        $this->amount = $amount;
        $this->reference = $reference;
        $this->description = $description;
        $this->intent = $payment;
        $this->assertCoherent();
    }

    /** @param list<PaymentAllocation> $allocations */
    public function replaceAllocations(array $allocations): void
    {
        $this->assertDraft();
        $this->payment()->replaceAllocations($allocations);
    }

    /** @param list<PaymentAllocation> $allocations */
    public function finalize(UserId $actor, DateTimeImmutable $at, array $allocations): void
    {
        if ($this->status === BankTransactionStatus::Finalized) {
            return;
        } $this->assertDraft();
        $payment = $this->payment();
        $payment->replaceAllocations($allocations);
        if ($allocations === [] || ! $payment->allocationTotal()->equals($this->amount->absolute())) {
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
        }
        if ($this->intent instanceof OtherBankTransactionIntent) {
            if (! $this->intent->amount()->equals($this->amount->absolute())) {
                throw new DomainException('Other intent amount must equal absolute bank transaction amount.');
            }

            return;
        }
        if (! $this->intent->amount()->equals($this->amount->absolute())) {
            throw new DomainException('Payment amount must equal absolute bank transaction amount.');
        } $expected = $this->amount->isPositive() ? PaymentType::CustomerReceipt : PaymentType::SupplierPayment;
        if ($this->intent->type() !== $expected) {
            throw new DomainException('Payment type must be derived from signed amount.');
        }
    }
}
