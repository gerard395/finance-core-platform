<?php

declare(strict_types=1);

namespace Tests\Integration\FinancialFlow;

use App\Application\Banking\CreateBankTransactionPostingRequest;
use App\Application\Sales\CreateSalesInvoicePostingRequest;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemStatus;
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
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Services\Matching;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\BankAccountId;
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
    public function test_sales_invoice_is_posted_and_fully_settled_through_banking(): void
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

        $invoiceId = $invoice->id();
        $invoiceNumber = $invoice->number();
        $invoiceLines = $invoice->lines();
        $invoiceRequest = (new CreateSalesInvoicePostingRequest)->execute(
            $invoice,
            $this->journalId('00000000-0000-4000-8000-000000000010'),
            $this->ledgerAccountId('00000000-0000-4000-8000-000000000011'),
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
            OpenItemType::Receivable,
            $invoiceAmount,
            $invoiceEntry->postingDate(),
        );
        self::assertSame($administrationId, $openItem->administrationId());
        self::assertSame($relationId, $openItem->relationId());
        self::assertSame($invoiceEntry->id(), $openItem->journalEntryId());
        self::assertSame(OpenItemType::Receivable, $openItem->type());

        $transaction = $this->bankTransaction($administrationId, $currency, '125');
        $payment = new Payment($this->paymentId(), $openItemId, new Money('125', $currency));
        $transaction->addPayment($payment);
        self::assertSame($openItemId, $payment->openItemId());

        $matchingResult = (new Matching)->match($transaction);
        self::assertTrue($matchingResult->isSuccess());
        self::assertSame($transaction, $matchingResult->transaction());
        self::assertTrue($matchingResult->matchedAmount()->equals($payment->amount()));

        $bankRequest = (new CreateBankTransactionPostingRequest)->execute(
            $transaction,
            $this->journalId('00000000-0000-4000-8000-000000000020'),
            $this->ledgerAccountId('00000000-0000-4000-8000-000000000021'),
            $this->ledgerAccountId('00000000-0000-4000-8000-000000000022'),
            $this->journalEntryLineId('00000000-0000-4000-8000-000000000023'),
            $this->journalEntryLineId('00000000-0000-4000-8000-000000000024'),
            new PostingDate(new DateTimeImmutable('2026-07-20')),
            new JournalEntryReference('BANK-2026-001'),
        );
        self::assertTrue($validation->validate($bankRequest)->isValid());
        $bankResult = $this->postingEngine($validation, '00000000-0000-4000-8000-000000000025')->post($bankRequest);
        self::assertTrue($bankResult->isSuccess());
        $bankEntry = $bankResult->journalEntry();
        self::assertNotNull($bankEntry);
        self::assertSame($administrationId, $bankRequest->administrationId());
        self::assertSame($administrationId, $bankEntry->administrationId());
        self::assertTrue($bankEntry->isPosted());
        $this->assertBalanced($bankRequest, $invoiceAmount);

        $openItem->applySettlement(
            $this->openItemSettlementId('00000000-0000-4000-8000-000000000026'),
            $bankEntry->postingDate(),
            $matchingResult->matchedAmount(),
            $bankEntry->id(),
        );
        self::assertTrue($openItem->openAmount()->isZero());
        self::assertTrue($openItem->isClosed());
        self::assertSame(OpenItemStatus::Closed, $openItem->status());
        self::assertSame('EUR', $openItem->openAmount()->currency()->code());
        self::assertSame('EUR', $transaction->amount()->currency()->code());
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

    public function test_failed_matching_does_not_mutate_open_item(): void
    {
        $currency = new Currency('EUR');
        $openItem = $this->openItem('125');
        $transaction = $this->bankTransaction($this->administrationId(), $currency, '125');
        $transaction->addPayment(new Payment($this->paymentId(), $openItem->id(), new Money('100', $currency)));

        $result = (new Matching)->match($transaction);

        self::assertFalse($result->isSuccess());
        self::assertSame('100', $result->matchedAmount()->amount());
        self::assertSame(BankTransactionStatus::Imported, $transaction->status());
        self::assertSame('125', $openItem->openAmount()->amount());
        self::assertTrue($openItem->isOpen());
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

    private function bankTransaction(AdministrationId $administrationId, Currency $currency, string $amount): BankTransaction
    {
        return new BankTransaction(
            new BankTransactionId(new Uuid('00000000-0000-4000-8000-000000000030')),
            new BankAccountId(new Uuid('00000000-0000-4000-8000-000000000031')),
            $administrationId,
            new DateTimeImmutable('2026-07-20'),
            new DateTimeImmutable('2026-07-20'),
            new Money($amount, $currency),
            new BankTransactionReference('BANK-2026-001'),
            new TransactionDescription('Customer payment'),
            BankTransactionStatus::Imported,
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

    private function paymentId(): PaymentId
    {
        return new PaymentId(new Uuid('00000000-0000-4000-8000-000000000006'));
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
