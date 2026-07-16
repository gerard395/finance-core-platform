<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\ValueObjects;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\MatchingError;
use App\Domain\Banking\ValueObjects\MatchingResult;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MatchingResultTest extends TestCase
{
    public function test_success_result_exposes_transaction_and_matched_amount(): void
    {
        $transaction = $this->createTransaction();
        $amount = new Money('100', new Currency('EUR'));
        $result = new MatchingResult($transaction, $amount);

        self::assertTrue($result->isSuccess());
        self::assertSame([], $result->errors());
        self::assertSame($amount, $result->matchedAmount());
        self::assertSame($transaction, $result->transaction());
    }

    public function test_failure_result_exposes_errors_immutably(): void
    {
        $error = new MatchingError(MatchingError::NO_PAYMENTS, 'No payments.');
        $errors = [$error];
        $result = new MatchingResult(
            $this->createTransaction(),
            Money::zero(new Currency('EUR')),
            $errors,
        );
        $errors[] = new MatchingError(MatchingError::AMOUNT_MISMATCH, 'Mismatch.');

        self::assertFalse($result->isSuccess());
        self::assertSame([$error], $result->errors());
    }

    private function createTransaction(): BankTransaction
    {
        return new BankTransaction(
            new BankTransactionId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new BankAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new DateTimeImmutable('2026-07-15'),
            new DateTimeImmutable('2026-07-16'),
            new Money('100', new Currency('EUR')),
            new BankTransactionReference('BANK-REF-001'),
            new TransactionDescription('Supplier payment'),
            BankTransactionStatus::Imported,
        );
    }
}
