<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\Entities;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BankTransactionTest extends TestCase
{
    public function test_constructor_exposes_all_immutable_context(): void
    {
        $transaction = $this->createTransaction();

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $transaction->id()->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $transaction->bankAccountId()->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440002', $transaction->administrationId()->toString());
        self::assertSame('2026-07-15', $transaction->bookingDate()->format('Y-m-d'));
        self::assertSame('2026-07-16', $transaction->valueDate()->format('Y-m-d'));
        self::assertSame('-125.5', $transaction->amount()->amount());
        self::assertSame('EUR', $transaction->amount()->currency()->code());
        self::assertSame('BANK-REF-001', $transaction->reference()->value());
        self::assertSame('Supplier payment', $transaction->description()->value());
        self::assertSame(BankTransactionStatus::Imported, $transaction->status());
    }

    public function test_payments_are_owned_and_managed_by_the_aggregate(): void
    {
        $transaction = $this->createTransaction();
        $first = $this->createPayment('550e8400-e29b-41d4-a716-446655440010');
        $second = $this->createPayment('550e8400-e29b-41d4-a716-446655440011');

        $transaction->addPayment($first);
        $transaction->addPayment($second);

        self::assertSame([$first, $second], $transaction->payments());
        self::assertTrue($transaction->hasPayment($first->id()));
        self::assertSame($first, $transaction->payment($first->id()));

        $transaction->removePayment($first->id());
        $transaction->removePayment($first->id());

        self::assertFalse($transaction->hasPayment($first->id()));
        self::assertNull($transaction->payment($first->id()));
    }

    public function test_duplicate_payment_identity_is_rejected(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addPayment($this->createPayment());

        $this->expectException(DomainException::class);
        $transaction->addPayment($this->createPayment());
    }

    public function test_payment_with_different_currency_is_rejected(): void
    {
        $transaction = $this->createTransaction();

        $this->expectException(DomainException::class);
        $transaction->addPayment($this->createPayment(currency: 'USD'));
    }

    #[DataProvider('immutablePaymentStatuses')]
    public function test_payments_cannot_be_changed_after_import(BankTransactionStatus $status): void
    {
        $transaction = $this->createTransaction(status: $status);

        try {
            $transaction->addPayment($this->createPayment());
            self::fail('Expected adding a payment after import to be rejected.');
        } catch (DomainException) {
            self::assertSame([], $transaction->payments());
        }

        $this->expectException(DomainException::class);
        $transaction->removePayment(new PaymentId(new Uuid('550e8400-e29b-41d4-a716-446655440010')));
    }

    /** @return array<string, array{BankTransactionStatus}> */
    public static function immutablePaymentStatuses(): array
    {
        return [
            'matched' => [BankTransactionStatus::Matched],
            'posted' => [BankTransactionStatus::Posted],
        ];
    }

    /** @param list<string> $transitions */
    #[DataProvider('validTransitions')]
    public function test_valid_status_transitions(array $transitions, BankTransactionStatus $expected): void
    {
        $transaction = $this->createTransaction();

        foreach ($transitions as $transition) {
            $transaction->{$transition}();
        }

        self::assertSame($expected, $transaction->status());
    }

    /** @return iterable<string, array{list<string>, BankTransactionStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'Imported to Matched' => [['match'], BankTransactionStatus::Matched];
        yield 'Matched to Posted' => [['match', 'post'], BankTransactionStatus::Posted];
    }

    /** @param list<string> $transitions */
    #[DataProvider('idempotentTransitions')]
    public function test_repeating_the_same_transition_is_idempotent(array $transitions, BankTransactionStatus $expected): void
    {
        $transaction = $this->createTransaction();

        foreach ($transitions as $transition) {
            $transaction->{$transition}();
        }

        self::assertSame($expected, $transaction->status());
    }

    /** @return iterable<string, array{list<string>, BankTransactionStatus}> */
    public static function idempotentTransitions(): iterable
    {
        yield 'Matched' => [['match', 'match'], BankTransactionStatus::Matched];
        yield 'Posted' => [['match', 'post', 'post'], BankTransactionStatus::Posted];
    }

    /** @param list<string> $transitions */
    #[DataProvider('invalidTransitions')]
    public function test_invalid_status_transitions_are_rejected(array $transitions): void
    {
        $transaction = $this->createTransaction();

        $this->expectException(DomainException::class);

        foreach ($transitions as $transition) {
            $transaction->{$transition}();
        }
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidTransitions(): iterable
    {
        yield 'Imported to Posted' => [['post']];
        yield 'Posted to Matched' => [['match', 'post', 'match']];
    }

    public function test_transitions_do_not_change_immutable_context(): void
    {
        $transaction = $this->createTransaction();
        $context = [
            $transaction->id(),
            $transaction->bankAccountId(),
            $transaction->administrationId(),
            $transaction->bookingDate(),
            $transaction->valueDate(),
            $transaction->amount(),
            $transaction->reference(),
            $transaction->description(),
        ];

        $transaction->match();
        $transaction->post();

        self::assertSame($context, [
            $transaction->id(),
            $transaction->bankAccountId(),
            $transaction->administrationId(),
            $transaction->bookingDate(),
            $transaction->valueDate(),
            $transaction->amount(),
            $transaction->reference(),
            $transaction->description(),
        ]);
    }

    public function test_out_of_scope_apis_are_not_exposed(): void
    {
        $transaction = $this->createTransaction();

        self::assertFalse(method_exists($transaction, 'matching'));
        self::assertFalse(method_exists($transaction, 'postingRequest'));
    }

    private function createTransaction(BankTransactionStatus $status = BankTransactionStatus::Imported): BankTransaction
    {
        return new BankTransaction(
            new BankTransactionId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new BankAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new DateTimeImmutable('2026-07-15'),
            new DateTimeImmutable('2026-07-16'),
            new Money('-125.50', new Currency('EUR')),
            new BankTransactionReference('BANK-REF-001'),
            new TransactionDescription('Supplier payment'),
            $status,
        );
    }

    private function createPayment(
        string $uuid = '550e8400-e29b-41d4-a716-446655440010',
        string $currency = 'EUR',
    ): Payment {
        return new Payment(
            new PaymentId(new Uuid($uuid)),
            new OpenItemId(new Uuid('550e8400-e29b-41d4-a716-446655440020')),
            new Money('25', new Currency($currency)),
        );
    }
}
