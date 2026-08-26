<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\Entities;

use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Entities\PaymentAllocation;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ManualBankTransactionTest extends TestCase
{
    public function test_signed_direction_exact_one_payment_multiple_allocations_and_finalize_immutability(): void
    {
        $tx = $this->transaction('150');
        self::assertSame(PaymentType::CustomerReceipt, $tx->payment()->type());
        self::assertSame(BankTransactionStatus::Draft, $tx->status());
        $allocations = [$this->allocation(1, '100')->finalized(OpenItemType::Receivable, OpenItemSide::Debit, $this->relation(), $this->ledger()), $this->allocation(2, '50')->finalized(OpenItemType::Receivable, OpenItemSide::Debit, $this->relation(), $this->ledger())];
        $actor = $this->user();
        $at = new DateTimeImmutable('2026-08-26T12:00:00Z');
        $tx->finalize($actor, $at, $allocations);
        self::assertSame(BankTransactionStatus::Finalized, $tx->status());
        self::assertTrue($tx->finalizedBy()?->equals($actor));
        self::assertEquals($at, $tx->finalizedAt());
        $this->expectException(DomainException::class);
        $tx->replaceAllocations([]);
    }

    public function test_negative_amount_derives_supplier_and_draft_can_cancel(): void
    {
        $tx = $this->transaction('-25');
        self::assertSame(PaymentType::SupplierPayment, $tx->payment()->type());
        $tx->cancel();
        self::assertSame(BankTransactionStatus::Cancelled, $tx->status());
        $this->expectException(DomainException::class);
        $tx->cancel();
    }

    public function test_zero_non_eur_duplicate_target_and_inexact_finalize_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        new Payment(new PaymentId($this->uuid(4)), PaymentType::CustomerReceipt, $this->relation(), new Money('10', new Currency('EUR')), [$this->allocation(1, '5'), new PaymentAllocation(new PaymentAllocationId($this->uuid(2)), new OpenItemId($this->uuid(31)), new Money('5', new Currency('EUR')))]);
    }

    private function transaction(string $amount): BankTransaction
    {
        $money = new Money($amount, new Currency('EUR'));
        $type = $money->isPositive() ? PaymentType::CustomerReceipt : PaymentType::SupplierPayment;

        return new BankTransaction(new BankTransactionId($this->uuid(10)), new AdministrationBankAccountId($this->uuid(11)), new AdministrationId($this->uuid(12)), new TransactionDate(new DateTimeImmutable('2026-08-26')), $money, new BankTransactionReference('REF'), new TransactionDescription('Description'), new Payment(new PaymentId($this->uuid(13)), $type, $this->relation(), $money->absolute()), BankTransactionStatus::Draft, $this->user(), new DateTimeImmutable('2026-08-26T10:00:00Z'));
    }

    private function allocation(int $n, string $amount): PaymentAllocation
    {
        return new PaymentAllocation(new PaymentAllocationId($this->uuid(20 + $n)), new OpenItemId($this->uuid(30 + $n)), new Money($amount, new Currency('EUR')));
    }

    private function relation(): RelationId
    {
        return new RelationId($this->uuid(40));
    }

    private function ledger(): LedgerAccountId
    {
        return new LedgerAccountId($this->uuid(41));
    }

    private function user(): UserId
    {
        return new UserId($this->uuid(42));
    }

    private function uuid(int $n): Uuid
    {
        return new Uuid(sprintf('b2800000-0000-4000-8000-%012d', $n));
    }
}
