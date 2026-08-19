<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Banking\CreateBankTransactionPostingRequest;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Services\Matching;
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

final class CreateBankTransactionPostingRequestTest extends TestCase
{
    public function test_positive_matched_transaction_creates_debit_bank_posting(): void
    {
        $transaction = $this->createMatchedTransaction('125.50');
        $journalId = new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440020'));
        $bankAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440021'));
        $counterAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440022'));
        $postingDate = new PostingDate(new DateTimeImmutable('2026-07-31'));
        $reference = new JournalEntryReference('BANK-REF-001');

        $request = $this->createRequest(
            $transaction,
            $journalId,
            $bankAccountId,
            $counterAccountId,
            $postingDate,
            $reference,
        );

        self::assertSame($transaction->administrationId(), $request->administrationId());
        self::assertSame($journalId, $request->journalId());
        self::assertSame($postingDate, $request->postingDate());
        self::assertSame($reference, $request->reference());
        self::assertCount(2, $request->lines());

        [$bankLine, $counterLine] = $request->lines();
        self::assertSame($bankAccountId, $bankLine->ledgerAccountId());
        self::assertSame('125.5', $bankLine->debit()?->amount());
        self::assertNull($bankLine->credit());
        self::assertSame($counterAccountId, $counterLine->ledgerAccountId());
        self::assertNull($counterLine->debit());
        self::assertSame('125.5', $counterLine->credit()?->amount());
        self::assertSame($bankLine->debit(), $counterLine->credit());
        self::assertSame('EUR', $bankLine->debit()?->currency()->code());

        $this->assertRequestCanBePosted($request);
        self::assertSame(BankTransactionStatus::Matched, $transaction->status());
        self::assertCount(1, $transaction->payments());
    }

    public function test_negative_matched_transaction_creates_credit_bank_posting(): void
    {
        $transaction = $this->createMatchedTransaction('-125.50');
        $bankAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440021'));
        $counterAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440022'));

        $request = $this->createRequest(
            $transaction,
            new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440020')),
            $bankAccountId,
            $counterAccountId,
            new PostingDate(new DateTimeImmutable('2026-07-31')),
            new JournalEntryReference('BANK-REF-001'),
        );

        self::assertCount(2, $request->lines());
        [$bankLine, $counterLine] = $request->lines();
        self::assertSame($bankAccountId, $bankLine->ledgerAccountId());
        self::assertNull($bankLine->debit());
        self::assertSame('125.5', $bankLine->credit()?->amount());
        self::assertSame($counterAccountId, $counterLine->ledgerAccountId());
        self::assertSame('125.5', $counterLine->debit()?->amount());
        self::assertNull($counterLine->credit());
        self::assertSame($bankLine->credit(), $counterLine->debit());
        self::assertSame('EUR', $bankLine->credit()?->currency()->code());

        $this->assertRequestCanBePosted($request);
        self::assertSame(BankTransactionStatus::Matched, $transaction->status());
        self::assertCount(1, $transaction->payments());
    }

    #[DataProvider('rejectedStatuses')]
    public function test_imported_and_posted_transactions_are_rejected(BankTransactionStatus $status): void
    {
        $transaction = $status === BankTransactionStatus::Imported
            ? $this->createTransaction('100')
            : $this->createMatchedTransaction('100');

        if ($status === BankTransactionStatus::Posted) {
            $transaction->post();
        }

        $this->expectException(DomainException::class);

        $this->createRequest(
            $transaction,
            new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440020')),
            new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440021')),
            new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440022')),
            new PostingDate(new DateTimeImmutable('2026-07-31')),
            new JournalEntryReference('BANK-REF-001'),
        );
    }

    /** @return array<string, array{BankTransactionStatus}> */
    public static function rejectedStatuses(): array
    {
        return [
            'imported' => [BankTransactionStatus::Imported],
            'posted' => [BankTransactionStatus::Posted],
        ];
    }

    private function createRequest(
        BankTransaction $transaction,
        JournalId $journalId,
        LedgerAccountId $bankAccountId,
        LedgerAccountId $counterAccountId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): PostingRequest {
        return (new CreateBankTransactionPostingRequest)->execute(
            $transaction,
            $journalId,
            $bankAccountId,
            $counterAccountId,
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440023')),
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440024')),
            $postingDate,
            $reference,
        );
    }

    private function assertRequestCanBePosted(PostingRequest $request): void
    {
        $validation = new PostingValidation;
        self::assertTrue($validation->validate($request)->isValid());

        $engine = new PostingEngine(
            $validation,
            static fn (): JournalEntryId => new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440025')),
        );
        $result = $engine->post($request);

        self::assertTrue($result->isSuccess());
        self::assertNotNull($result->journalEntry());
        self::assertSame($request->administrationId(), $result->journalEntry()->administrationId());
        self::assertTrue($result->journalEntry()->isPosted());
        self::assertCount(2, $result->journalEntry()->lines());
    }

    private function createMatchedTransaction(string $amount): BankTransaction
    {
        $transaction = $this->createTransaction($amount);
        $transaction->addPayment(new Payment(
            new PaymentId(new Uuid('550e8400-e29b-41d4-a716-446655440010')),
            new OpenItemId(new Uuid('550e8400-e29b-41d4-a716-446655440011')),
            $transaction->amount()->absolute(),
        ));

        $result = (new Matching)->match($transaction);
        self::assertTrue($result->isSuccess());

        return $transaction;
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
            new TransactionDescription('Bank payment'),
            BankTransactionStatus::Imported,
        );
    }
}
