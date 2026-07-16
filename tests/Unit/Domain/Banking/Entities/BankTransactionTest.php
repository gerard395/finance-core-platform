<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
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

        self::assertFalse(method_exists($transaction, 'payments'));
        self::assertFalse(method_exists($transaction, 'matching'));
        self::assertFalse(method_exists($transaction, 'postingRequest'));
    }

    private function createTransaction(): BankTransaction
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
            BankTransactionStatus::Imported,
        );
    }
}
