<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\Services;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Services\Matching;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\MatchingError;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MatchingTest extends TestCase
{
    public function test_transaction_without_payments_fails_without_status_change(): void
    {
        $transaction = $this->createTransaction('100');

        $result = (new Matching)->match($transaction);

        self::assertFalse($result->isSuccess());
        self::assertSame(MatchingError::NO_PAYMENTS, $result->errors()[0]->code());
        self::assertSame('0', $result->matchedAmount()->amount());
        self::assertSame(BankTransactionStatus::Imported, $transaction->status());
    }

    public function test_one_fully_allocated_payment_matches_transaction(): void
    {
        $transaction = $this->createTransaction('100');
        $transaction->addPayment($this->createPayment('100'));

        $result = (new Matching)->match($transaction);

        self::assertTrue($result->isSuccess());
        self::assertSame([], $result->errors());
        self::assertSame('100', $result->matchedAmount()->amount());
        self::assertSame($transaction, $result->transaction());
        self::assertSame(BankTransactionStatus::Matched, $transaction->status());
    }

    public function test_multiple_exact_decimal_payments_match_without_floating_point_deviation(): void
    {
        $transaction = $this->createTransaction('0.3');
        $transaction->addPayment($this->createPayment('0.1'));
        $transaction->addPayment($this->createPayment('0.2', '550e8400-e29b-41d4-a716-446655440011'));

        $result = (new Matching)->match($transaction);

        self::assertTrue($result->isSuccess());
        self::assertSame('0.3', $result->matchedAmount()->amount());
        self::assertSame(BankTransactionStatus::Matched, $transaction->status());
    }

    #[DataProvider('mismatchedAllocations')]
    public function test_mismatched_payment_sum_fails_without_mutation(string $transactionAmount, array $paymentAmounts): void
    {
        $transaction = $this->createTransaction($transactionAmount);

        foreach ($paymentAmounts as $index => $amount) {
            $transaction->addPayment($this->createPayment($amount, sprintf('550e8400-e29b-41d4-a716-4466554400%02d', $index + 10)));
        }

        $payments = $transaction->payments();
        $result = (new Matching)->match($transaction);

        self::assertFalse($result->isSuccess());
        self::assertSame(MatchingError::AMOUNT_MISMATCH, $result->errors()[0]->code());
        self::assertSame(BankTransactionStatus::Imported, $transaction->status());
        self::assertSame($payments, $transaction->payments());
    }

    /** @return array<string, array{string, list<string>}> */
    public static function mismatchedAllocations(): array
    {
        return [
            'too low' => ['100', ['40', '50']],
            'too high' => ['100', ['60', '50']],
        ];
    }

    public function test_negative_transaction_matches_against_absolute_amount(): void
    {
        $transaction = $this->createTransaction('-125.5');
        $transaction->addPayment($this->createPayment('125.5'));

        $result = (new Matching)->match($transaction);

        self::assertTrue($result->isSuccess());
        self::assertSame('125.5', $result->matchedAmount()->amount());
        self::assertSame(BankTransactionStatus::Matched, $transaction->status());
    }

    public function test_matching_an_already_matched_transaction_is_idempotent(): void
    {
        $transaction = $this->createTransaction('100');
        $transaction->addPayment($this->createPayment('100'));
        $matching = new Matching;

        $first = $matching->match($transaction);
        $second = $matching->match($transaction);

        self::assertTrue($first->isSuccess());
        self::assertTrue($second->isSuccess());
        self::assertSame('100', $second->matchedAmount()->amount());
        self::assertSame(BankTransactionStatus::Matched, $transaction->status());
    }

    public function test_posted_transaction_is_rejected(): void
    {
        $transaction = $this->createTransaction('100');
        $transaction->addPayment($this->createPayment('100'));
        (new Matching)->match($transaction);
        $transaction->post();

        $result = (new Matching)->match($transaction);

        self::assertFalse($result->isSuccess());
        self::assertSame(MatchingError::INVALID_STATUS, $result->errors()[0]->code());
        self::assertSame(BankTransactionStatus::Posted, $transaction->status());
    }

    public function test_matching_exposes_no_posting_or_creation_api(): void
    {
        $matching = new Matching;

        self::assertFalse(method_exists($matching, 'createPayment'));
        self::assertFalse(method_exists($matching, 'journalEntry'));
        self::assertFalse(method_exists($matching, 'postingRequest'));
    }

    private function createTransaction(string $amount): BankTransaction
    {
        return new BankTransaction(
            new BankTransactionId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new BankAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new DateTimeImmutable('2026-07-15'),
            new DateTimeImmutable('2026-07-16'),
            new Money($amount, new Currency('EUR')),
            new BankTransactionReference('BANK-REF-001'),
            new TransactionDescription('Supplier payment'),
            BankTransactionStatus::Imported,
        );
    }

    private function createPayment(
        string $amount,
        string $uuid = '550e8400-e29b-41d4-a716-446655440010',
    ): Payment {
        return new Payment(
            new PaymentId(new Uuid($uuid)),
            new OpenItemId(new Uuid('550e8400-e29b-41d4-a716-446655440020')),
            new Money($amount, new Currency('EUR')),
        );
    }
}
