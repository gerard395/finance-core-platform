<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\CreateSalesInvoicePostingRequest;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreateSalesInvoicePostingRequestTest extends TestCase
{
    public function test_finalized_invoice_creates_valid_request_that_posting_engine_can_process(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addLine($this->createInvoiceLine('550e8400-e29b-41d4-a716-446655440010', '2', '10'));
        $invoice->addLine($this->createInvoiceLine('550e8400-e29b-41d4-a716-446655440011', '3', '2.5'));
        $invoice->finalize();
        $journalId = new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440020'));
        $debtorAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440021'));
        $revenueAccountId = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440022'));
        $postingDate = new PostingDate(new DateTimeImmutable('2026-07-31'));
        $reference = new JournalEntryReference('SINV-001');

        $request = (new CreateSalesInvoicePostingRequest)->execute(
            $invoice,
            $journalId,
            $debtorAccountId,
            $revenueAccountId,
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440023')),
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440024')),
            $postingDate,
            $reference,
        );

        self::assertSame($journalId, $request->journalId());
        self::assertSame($postingDate, $request->postingDate());
        self::assertSame($reference, $request->reference());
        self::assertCount(2, $request->lines());

        [$debtorLine, $revenueLine] = $request->lines();
        self::assertInstanceOf(JournalEntryLine::class, $debtorLine);
        self::assertSame($debtorAccountId, $debtorLine->ledgerAccountId());
        self::assertSame('27.5', $debtorLine->debit()?->amount());
        self::assertNull($debtorLine->credit());
        self::assertSame($revenueAccountId, $revenueLine->ledgerAccountId());
        self::assertNull($revenueLine->debit());
        self::assertSame('27.5', $revenueLine->credit()?->amount());
        self::assertSame('EUR', $debtorLine->debit()?->currency()->code());
        self::assertSame('EUR', $revenueLine->credit()?->currency()->code());

        $validation = new PostingValidation;
        self::assertTrue($validation->validate($request)->isValid());

        $engine = new PostingEngine(
            $validation,
            static fn (): JournalEntryId => new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440025')),
        );
        $result = $engine->post($request);

        self::assertTrue($result->isSuccess());
        self::assertNotNull($result->journalEntry());
        self::assertTrue($result->journalEntry()->isPosted());
        self::assertCount(2, $result->journalEntry()->lines());
    }

    #[DataProvider('rejectedStatuses')]
    public function test_invoice_before_finalization_or_after_cancellation_is_rejected(SalesInvoiceStatus $status): void
    {
        $invoice = $this->createInvoice();
        $invoice->addLine($this->createInvoiceLine());

        if ($status === SalesInvoiceStatus::Cancelled) {
            $invoice->cancel();
        }

        $this->expectException(DomainException::class);

        (new CreateSalesInvoicePostingRequest)->execute(
            $invoice,
            new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440020')),
            new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440021')),
            new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440022')),
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440023')),
            new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440024')),
            new PostingDate(new DateTimeImmutable('2026-07-31')),
            new JournalEntryReference('SINV-001'),
        );
    }

    /** @return array<string, array{SalesInvoiceStatus}> */
    public static function rejectedStatuses(): array
    {
        return [
            'draft' => [SalesInvoiceStatus::Draft],
            'cancelled' => [SalesInvoiceStatus::Cancelled],
        ];
    }

    private function createInvoice(): SalesInvoice
    {
        return new SalesInvoice(
            new SalesInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new SalesInvoiceNumber('sinv-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            new DateTimeImmutable('2026-07-15'),
            new DateTimeImmutable('2026-08-14'),
            null,
            SalesInvoiceStatus::Draft,
        );
    }

    private function createInvoiceLine(
        string $uuid = '550e8400-e29b-41d4-a716-446655440010',
        string $quantity = '1',
        string $unitPrice = '10',
    ): SalesInvoiceLine {
        return new SalesInvoiceLine(
            new SalesInvoiceLineId(new Uuid($uuid)),
            new LineDescription('Consulting services'),
            new Quantity($quantity),
            new Money($unitPrice, new Currency('EUR')),
        );
    }
}
