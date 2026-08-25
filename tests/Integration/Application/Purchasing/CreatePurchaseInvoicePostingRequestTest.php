<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Purchasing\CreatePurchaseInvoicePostingRequest;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Entities\PurchaseInvoiceLine;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\PurchaseInvoiceTestFactory;

final class CreatePurchaseInvoicePostingRequestTest extends TestCase
{
    public function test_finalized_invoice_creates_valid_request_that_posting_engine_can_process(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addLine($this->createInvoiceLine('550e8400-e29b-41d4-a716-446655440010', '2', '10'));
        $invoice->addLine($this->createInvoiceLine('550e8400-e29b-41d4-a716-446655440011', '3', '2.5'));
        $invoice->finalize(new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440099')), new DateTimeImmutable('2026-07-16'));
        $journalId = new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440020'));
        $creditorAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440021'));
        $expenseAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440022'));
        $postingDate = new PostingDate(new DateTimeImmutable('2026-07-31'));
        $reference = new JournalEntryReference('PINV-001');

        $request = (new CreatePurchaseInvoicePostingRequest)->execute(
            $invoice,
            $journalId,
            $creditorAccountId,
            $expenseAccountId,
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440023')),
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440024')),
            $postingDate,
            $reference,
        );

        self::assertSame($invoice->administrationId(), $request->administrationId());
        self::assertSame($journalId, $request->journalId());
        self::assertSame($postingDate, $request->postingDate());
        self::assertSame($reference, $request->reference());
        self::assertCount(2, $request->lines());

        [$expenseLine, $creditorLine] = $request->lines();
        self::assertInstanceOf(JournalEntryLine::class, $expenseLine);
        self::assertSame($expenseAccountId, $expenseLine->ledgerAccountId());
        self::assertSame('27.5', $expenseLine->debit()?->amount());
        self::assertNull($expenseLine->credit());
        self::assertSame($creditorAccountId, $creditorLine->ledgerAccountId());
        self::assertNull($creditorLine->debit());
        self::assertSame('27.5', $creditorLine->credit()?->amount());
        self::assertSame('EUR', $expenseLine->debit()?->currency()->code());
        self::assertSame('EUR', $creditorLine->credit()?->currency()->code());

        $validation = new PostingValidation;
        self::assertTrue($validation->validate($request)->isValid());

        $engine = new PostingEngine(
            $validation,
            static fn (): JournalEntryId => new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440025')),
        );
        $result = $engine->post($request);

        self::assertTrue($result->isSuccess());
        self::assertNotNull($result->journalEntry());
        self::assertSame($invoice->administrationId(), $result->journalEntry()->administrationId());
        self::assertTrue($result->journalEntry()->isPosted());
        self::assertCount(2, $result->journalEntry()->lines());
    }

    #[DataProvider('rejectedStatuses')]
    public function test_invoice_before_finalization_or_after_cancellation_is_rejected(PurchaseInvoiceStatus $status): void
    {
        $invoice = $this->createInvoice();
        $invoice->addLine($this->createInvoiceLine());

        if ($status === PurchaseInvoiceStatus::Cancelled) {
            $invoice->cancel();
        }

        $this->expectException(DomainException::class);

        (new CreatePurchaseInvoicePostingRequest)->execute(
            $invoice,
            new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440020')),
            new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440021')),
            new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440022')),
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440023')),
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440024')),
            new PostingDate(new DateTimeImmutable('2026-07-31')),
            new JournalEntryReference('PINV-001'),
        );
    }

    /** @return array<string, array{PurchaseInvoiceStatus}> */
    public static function rejectedStatuses(): array
    {
        return [
            'draft' => [PurchaseInvoiceStatus::Draft],
            'cancelled' => [PurchaseInvoiceStatus::Cancelled],
        ];
    }

    private function createInvoice(): PurchaseInvoice
    {
        return PurchaseInvoiceTestFactory::invoice();
    }

    private function createInvoiceLine(
        string $uuid = '550e8400-e29b-41d4-a716-446655440010',
        string $quantity = '1',
        string $unitPrice = '10',
    ): PurchaseInvoiceLine {
        return PurchaseInvoiceTestFactory::line($uuid, $quantity, $unitPrice);
    }
}
