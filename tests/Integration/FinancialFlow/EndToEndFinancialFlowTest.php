<?php

declare(strict_types=1);

namespace Tests\Integration\FinancialFlow;

use App\Application\Sales\CreateSalesInvoicePostingRequest;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\RelationId;
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
use PHPUnit\Framework\TestCase;

final class EndToEndFinancialFlowTest extends TestCase
{
    public function test_sales_invoice_is_posted_and_creates_compatible_open_item_truth(): void
    {
        $currency = new Currency('EUR');
        $administrationId = $this->administrationId();
        $relationId = $this->relationId();
        $invoice = $this->invoice($administrationId, $relationId, $currency);
        $invoice->addLine(new SalesInvoiceLine(
            $this->salesInvoiceLineId(),
            new LineDescription('Consulting services'),
            new Quantity('2'),
            new Money('62.50', $currency),
        ));
        $invoice->finalize();
        $receivableAccountId = $this->ledgerAccountId('00000000-0000-4000-8000-000000000011');

        $invoiceId = $invoice->id();
        $invoiceNumber = $invoice->number();
        $invoiceLines = $invoice->lines();
        $invoiceRequest = (new CreateSalesInvoicePostingRequest)->execute(
            $invoice,
            $this->journalId('00000000-0000-4000-8000-000000000010'),
            $receivableAccountId,
            $this->ledgerAccountId('00000000-0000-4000-8000-000000000012'),
            $this->journalEntryLineId('00000000-0000-4000-8000-000000000013'),
            $this->journalEntryLineId('00000000-0000-4000-8000-000000000014'),
            new PostingDate(new DateTimeImmutable('2026-07-15')),
            new JournalEntryReference('SINV-2026-001'),
        );

        $validation = new PostingValidation;
        self::assertTrue($validation->validate($invoiceRequest)->isValid());
        $invoiceResult = $this->postingEngine($validation, '00000000-0000-4000-8000-000000000015')->post($invoiceRequest);
        self::assertTrue($invoiceResult->isSuccess());
        $invoiceEntry = $invoiceResult->journalEntry();
        self::assertNotNull($invoiceEntry);
        self::assertSame($administrationId, $invoiceRequest->administrationId());
        self::assertSame($administrationId, $invoiceEntry->administrationId());
        self::assertTrue($invoiceEntry->isPosted());
        $this->assertBalanced($invoiceRequest, new Money('125', $currency));

        $openItemId = $this->openItemId();
        $invoiceAmount = new Money('125', $currency);
        $openItem = new OpenItem(
            $openItemId,
            $administrationId,
            $relationId,
            $invoiceEntry->id(),
            $receivableAccountId,
            OpenItemType::Receivable,
            $invoiceAmount,
            $invoiceEntry->postingDate(),
        );
        self::assertSame($administrationId, $openItem->administrationId());
        self::assertSame($relationId, $openItem->relationId());
        self::assertSame($invoiceEntry->id(), $openItem->journalEntryId());
        self::assertSame(OpenItemType::Receivable, $openItem->type());

        self::assertTrue($openItem->isOpen());
        self::assertSame('125', $openItem->openAmount()->amount());
        self::assertSame('EUR', $openItem->openAmount()->currency()->code());
        self::assertSame($invoiceId, $invoice->id());
        self::assertSame($invoiceNumber, $invoice->number());
        self::assertSame($invoiceLines, $invoice->lines());
        self::assertSame(SalesInvoiceStatus::Finalized, $invoice->status());
    }

    public function test_partial_payment_does_not_close_open_item(): void
    {
        $openItem = $this->openItem('125');

        $openItem->applySettlement(
            $this->openItemSettlementId('00000000-0000-4000-8000-000000000041'),
            new PostingDate(new DateTimeImmutable('2026-07-20')),
            new Money('100', new Currency('EUR')),
            new JournalEntryId(new Uuid('00000000-0000-4000-8000-000000000042')),
        );

        self::assertSame('25', $openItem->openAmount()->amount());
        self::assertTrue($openItem->isPartiallySettled());
        self::assertFalse($openItem->isClosed());
    }

    public function test_settlement_above_open_amount_is_rejected_without_mutation(): void
    {
        $openItem = $this->openItem('125');

        try {
            $openItem->applySettlement(
                $this->openItemSettlementId('00000000-0000-4000-8000-000000000043'),
                new PostingDate(new DateTimeImmutable('2026-07-20')),
                new Money('125.01', new Currency('EUR')),
                new JournalEntryId(new Uuid('00000000-0000-4000-8000-000000000044')),
            );
            self::fail('An excessive settlement must be rejected.');
        } catch (DomainException) {
            self::assertSame('125', $openItem->openAmount()->amount());
            self::assertTrue($openItem->isOpen());
        }
    }

    private function invoice(AdministrationId $administrationId, RelationId $relationId, Currency $currency): SalesInvoice
    {
        return new SalesInvoice(
            new SalesInvoiceId(new Uuid('00000000-0000-4000-8000-000000000001')),
            new SalesInvoiceNumber('sinv-2026-001'),
            $administrationId,
            new CustomerId($relationId->uuid()),
            $currency,
            new DateTimeImmutable('2026-07-15'),
            new DateTimeImmutable('2026-08-14'),
            null,
            SalesInvoiceStatus::Draft,
        );
    }

    private function openItem(string $amount): OpenItem
    {
        $money = new Money($amount, new Currency('EUR'));

        return new OpenItem(
            $this->openItemId(),
            $this->administrationId(),
            $this->relationId(),
            new JournalEntryId(new Uuid('00000000-0000-4000-8000-000000000040')),
            new LedgerAccountId(new Uuid('00000000-0000-4000-8000-000000000010')),
            OpenItemType::Receivable,
            $money,
            new PostingDate(new DateTimeImmutable('2026-07-15')),
        );
    }

    private function assertBalanced(PostingRequest $request, Money $expected): void
    {
        self::assertCount(2, $request->lines());
        $debit = Money::zero($expected->currency());
        $credit = Money::zero($expected->currency());

        foreach ($request->lines() as $line) {
            $debit = $debit->add($line->debit() ?? Money::zero($expected->currency()));
            $credit = $credit->add($line->credit() ?? Money::zero($expected->currency()));
        }

        self::assertTrue($expected->equals($debit));
        self::assertTrue($expected->equals($credit));
        self::assertTrue($debit->equals($credit));
    }

    private function postingEngine(PostingValidation $validation, string $journalEntryId): PostingEngine
    {
        return new PostingEngine(
            $validation,
            static fn (): JournalEntryId => new JournalEntryId(new Uuid($journalEntryId)),
        );
    }

    private function administrationId(): AdministrationId
    {
        return new AdministrationId(new Uuid('00000000-0000-4000-8000-000000000002'));
    }

    private function relationId(): RelationId
    {
        return new RelationId(new Uuid('00000000-0000-4000-8000-000000000003'));
    }

    private function salesInvoiceLineId(): SalesInvoiceLineId
    {
        return new SalesInvoiceLineId(new Uuid('00000000-0000-4000-8000-000000000004'));
    }

    private function openItemId(): OpenItemId
    {
        return new OpenItemId(new Uuid('00000000-0000-4000-8000-000000000005'));
    }

    private function openItemSettlementId(string $uuid): OpenItemSettlementId
    {
        return new OpenItemSettlementId(new Uuid($uuid));
    }

    private function journalId(string $uuid): JournalId
    {
        return new JournalId(new Uuid($uuid));
    }

    private function ledgerAccountId(string $uuid): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid($uuid));
    }

    private function journalEntryLineId(string $uuid): JournalEntryLineId
    {
        return new JournalEntryLineId(new Uuid($uuid));
    }
}
