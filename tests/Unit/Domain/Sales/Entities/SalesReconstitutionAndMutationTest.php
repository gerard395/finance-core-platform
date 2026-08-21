<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesCreditInvoiceLine;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
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
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SalesReconstitutionAndMutationTest extends TestCase
{
    public function test_quotation_reconstitution_and_draft_mutations_preserve_context(): void
    {
        $line = $this->quotationLine('1', '2', '10');
        $input = [$line];
        $quotation = Quotation::reconstitute($this->quotationId(), new QuotationNumber('Q000001'), $this->administrationId(), $this->customerId(), $this->eur(), QuotationStatus::Sent, new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), $input);

        self::assertSame(QuotationStatus::Sent, $quotation->status());
        self::assertSame($input, $quotation->lines());
        self::assertSame([$line], $input);
        self::assertSame('20', $quotation->total()->amount());
        self::assertSame('Q000001', $quotation->number()->value());
        $this->assertLocked(fn () => $quotation->updateLine($this->quotationLine('1', '3', '10')));
        $this->assertLocked(fn () => $quotation->changeDates(new DateTimeImmutable('2026-08-02'), null));

        $draft = Quotation::reconstitute($this->quotationId(), new QuotationNumber('Q000001'), $this->administrationId(), $this->customerId(), $this->eur(), QuotationStatus::Draft, new DateTimeImmutable('2026-08-01'), null, []);
        self::assertSame('0', $draft->total()->amount());
        $draft->addLine($line);
        $replacement = $this->quotationLine('1', '3', '10');
        $draft->updateLine($replacement);
        $draft->changeDates(new DateTimeImmutable('2026-08-02'), new DateTimeImmutable('2026-09-01'));
        self::assertSame($replacement, $draft->line($replacement->id()));
        self::assertSame('2026-08-02', $draft->quotationDate()->format('Y-m-d'));
        self::assertSame('30', $draft->total()->amount());
    }

    public function test_order_reconstitution_and_draft_mutations_preserve_source_and_context(): void
    {
        $source = $this->quotationId('9');
        $line = $this->orderLine('1', '2', '10');
        $input = [$line];
        $order = Order::reconstitute($this->orderId(), new OrderNumber('O000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), $source, OrderStatus::FullyInvoiced, $input);

        self::assertSame(OrderStatus::FullyInvoiced, $order->status());
        self::assertSame($source, $order->sourceQuotationId());
        self::assertSame($input, $order->lines());
        self::assertSame('20', $order->total()->amount());
        $this->assertLocked(fn () => $order->updateLine($this->orderLine('1', '3', '10')));
        $this->assertLocked(fn () => $order->changeOrderDate(new DateTimeImmutable('2026-08-02')));

        $draft = Order::reconstitute($this->orderId(), new OrderNumber('O000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), $source, OrderStatus::Draft, []);
        $draft->addLine($line);
        $replacement = $this->orderLine('1', '4', '10');
        $draft->updateLine($replacement);
        $draft->changeOrderDate(new DateTimeImmutable('2026-08-02'));
        self::assertSame($replacement, $draft->line($replacement->id()));
        self::assertSame('40', $draft->total()->amount());
    }

    public function test_invoice_reconstitution_and_draft_mutations_preserve_source_and_context(): void
    {
        $source = $this->orderId('9');
        $line = $this->invoiceLine('1', '2', '10');
        $input = [$line];
        $invoice = SalesInvoice::reconstitute($this->invoiceId(), new SalesInvoiceNumber('F000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), $source, SalesInvoiceStatus::Paid, $input);

        self::assertSame(SalesInvoiceStatus::Paid, $invoice->status());
        self::assertSame($source, $invoice->sourceOrderId());
        self::assertSame($input, $invoice->lines());
        self::assertSame('20', $invoice->total()->amount());
        $this->assertLocked(fn () => $invoice->updateLine($this->invoiceLine('1', '3', '10')));
        $this->assertLocked(fn () => $invoice->changeDates(new DateTimeImmutable('2026-08-02'), new DateTimeImmutable('2026-09-01')));

        $draft = SalesInvoice::reconstitute($this->invoiceId(), new SalesInvoiceNumber('F000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), $source, SalesInvoiceStatus::Draft, []);
        $draft->addLine($line);
        $replacement = $this->invoiceLine('1', '5', '10');
        $draft->updateLine($replacement);
        $draft->changeDates(new DateTimeImmutable('2026-08-02'), new DateTimeImmutable('2026-09-01'));
        self::assertSame($replacement, $draft->line($replacement->id()));
        self::assertSame('50', $draft->total()->amount());
    }

    public function test_credit_reconstitution_and_draft_mutations_preserve_nullable_source_contract(): void
    {
        $source = $this->invoiceId('9');
        $line = $this->creditLine('1', '2', '10');
        $input = [$line];
        $credit = SalesCreditInvoice::reconstitute($this->creditId(), new SalesCreditInvoiceNumber('C000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), $source, SalesCreditInvoiceStatus::Posted, $input);

        self::assertSame(SalesCreditInvoiceStatus::Posted, $credit->status());
        self::assertSame($source, $credit->sourceInvoiceId());
        self::assertSame($input, $credit->lines());
        self::assertSame('20', $credit->total()->amount());
        $this->assertLocked(fn () => $credit->updateLine($this->creditLine('1', '3', '10')));
        $this->assertLocked(fn () => $credit->changeCreditInvoiceDate(new DateTimeImmutable('2026-08-02')));

        $draft = SalesCreditInvoice::reconstitute($this->creditId(), new SalesCreditInvoiceNumber('C000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, SalesCreditInvoiceStatus::Draft, []);
        self::assertNull($draft->sourceInvoiceId());
        $draft->addLine($line);
        $replacement = $this->creditLine('1', '6', '10');
        $draft->updateLine($replacement);
        $draft->changeCreditInvoiceDate(new DateTimeImmutable('2026-08-02'));
        self::assertSame($replacement, $draft->line($replacement->id()));
        self::assertSame('60', $draft->total()->amount());
    }

    public function test_reconstitution_rejects_duplicate_lines_mixed_currency_and_impossible_empty_lifecycle_state(): void
    {
        $this->assertInvalid(fn () => Quotation::reconstitute($this->quotationId(), new QuotationNumber('Q000001'), $this->administrationId(), $this->customerId(), $this->eur(), QuotationStatus::Draft, new DateTimeImmutable('2026-08-01'), null, [$this->quotationLine('1'), $this->quotationLine('1')]));
        $this->assertInvalid(fn () => Quotation::reconstitute($this->quotationId(), new QuotationNumber('Q000001'), $this->administrationId(), $this->customerId(), $this->eur(), QuotationStatus::Draft, new DateTimeImmutable('2026-08-01'), null, [$this->quotationLine('1', currency: 'USD')]));
        $this->assertInvalid(fn () => Quotation::reconstitute($this->quotationId(), new QuotationNumber('Q000001'), $this->administrationId(), $this->customerId(), $this->eur(), QuotationStatus::Sent, new DateTimeImmutable('2026-08-01'), null, []));

        $this->assertInvalid(fn () => Order::reconstitute($this->orderId(), new OrderNumber('O000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, OrderStatus::Draft, [$this->orderLine('1'), $this->orderLine('1')]));
        $this->assertInvalid(fn () => Order::reconstitute($this->orderId(), new OrderNumber('O000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, OrderStatus::Draft, [$this->orderLine('1', currency: 'USD')]));
        $this->assertInvalid(fn () => Order::reconstitute($this->orderId(), new OrderNumber('O000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, OrderStatus::Confirmed, []));

        $this->assertInvalid(fn () => SalesInvoice::reconstitute($this->invoiceId(), new SalesInvoiceNumber('F000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), null, SalesInvoiceStatus::Draft, [$this->invoiceLine('1'), $this->invoiceLine('1')]));
        $this->assertInvalid(fn () => SalesInvoice::reconstitute($this->invoiceId(), new SalesInvoiceNumber('F000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), null, SalesInvoiceStatus::Draft, [$this->invoiceLine('1', currency: 'USD')]));
        $this->assertInvalid(fn () => SalesInvoice::reconstitute($this->invoiceId(), new SalesInvoiceNumber('F000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), null, SalesInvoiceStatus::Finalized, []));

        $this->assertInvalid(fn () => SalesCreditInvoice::reconstitute($this->creditId(), new SalesCreditInvoiceNumber('C000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, SalesCreditInvoiceStatus::Draft, [$this->creditLine('1'), $this->creditLine('1')]));
        $this->assertInvalid(fn () => SalesCreditInvoice::reconstitute($this->creditId(), new SalesCreditInvoiceNumber('C000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, SalesCreditInvoiceStatus::Draft, [$this->creditLine('1', currency: 'USD')]));
        $this->assertInvalid(fn () => SalesCreditInvoice::reconstitute($this->creditId(), new SalesCreditInvoiceNumber('C000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, SalesCreditInvoiceStatus::Posted, []));
    }

    public function test_all_draft_line_paths_reject_currency_mismatch_and_unknown_update(): void
    {
        $quotation = Quotation::reconstitute($this->quotationId(), new QuotationNumber('Q000001'), $this->administrationId(), $this->customerId(), $this->eur(), QuotationStatus::Draft, new DateTimeImmutable('2026-08-01'), null, []);
        $order = Order::reconstitute($this->orderId(), new OrderNumber('O000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, OrderStatus::Draft, []);
        $invoice = SalesInvoice::reconstitute($this->invoiceId(), new SalesInvoiceNumber('F000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), null, SalesInvoiceStatus::Draft, []);
        $credit = SalesCreditInvoice::reconstitute($this->creditId(), new SalesCreditInvoiceNumber('C000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), null, SalesCreditInvoiceStatus::Draft, []);

        $this->assertInvalid(fn () => $quotation->addLine($this->quotationLine('1', currency: 'USD')));
        $this->assertInvalid(fn () => $order->updateLine($this->orderLine('1')));
        $this->assertInvalid(fn () => $invoice->addLine($this->invoiceLine('1', currency: 'USD')));
        $this->assertInvalid(fn () => $credit->updateLine($this->creditLine('1')));
    }

    public function test_date_mutations_preserve_previous_state_when_cross_field_validation_fails(): void
    {
        $quotation = Quotation::reconstitute($this->quotationId(), new QuotationNumber('Q000001'), $this->administrationId(), $this->customerId(), $this->eur(), QuotationStatus::Draft, new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), []);
        $invoice = SalesInvoice::reconstitute($this->invoiceId(), new SalesInvoiceNumber('F000001'), $this->administrationId(), $this->customerId(), $this->eur(), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), null, SalesInvoiceStatus::Draft, []);

        try {
            $quotation->changeDates(new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-08-31'));
            self::fail('Invalid quotation dates must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame('2026-08-01', $quotation->quotationDate()->format('Y-m-d'));
        }
        try {
            $invoice->changeDates(new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-08-31'));
            self::fail('Invalid invoice dates must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame('2026-08-01', $invoice->invoiceDate()->format('Y-m-d'));
        }
    }

    private function assertLocked(callable $operation): void
    {
        $this->assertInvalid($operation);
    }

    private function assertInvalid(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected invalid Sales aggregate state to be rejected.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
    }

    private function quotationLine(string $id, string $quantity = '1', string $price = '10', string $currency = 'EUR'): QuotationLine
    {
        return new QuotationLine(new QuotationLineId($this->uuid('1', $id)), new LineDescription('Quotation line'), new Quantity($quantity), new Money($price, new Currency($currency)));
    }

    private function orderLine(string $id, string $quantity = '1', string $price = '10', string $currency = 'EUR'): OrderLine
    {
        return new OrderLine(new OrderLineId($this->uuid('2', $id)), new LineDescription('Order line'), new Quantity($quantity), new Money($price, new Currency($currency)));
    }

    private function invoiceLine(string $id, string $quantity = '1', string $price = '10', string $currency = 'EUR'): SalesInvoiceLine
    {
        return new SalesInvoiceLine(new SalesInvoiceLineId($this->uuid('3', $id)), new LineDescription('Invoice line'), new Quantity($quantity), new Money($price, new Currency($currency)));
    }

    private function creditLine(string $id, string $quantity = '1', string $price = '10', string $currency = 'EUR'): SalesCreditInvoiceLine
    {
        return new SalesCreditInvoiceLine(new SalesCreditInvoiceLineId($this->uuid('4', $id)), new LineDescription('Credit line'), new Quantity($quantity), new Money($price, new Currency($currency)));
    }

    private function quotationId(string $id = '1'): QuotationId
    {
        return new QuotationId($this->uuid('5', $id));
    }

    private function orderId(string $id = '1'): OrderId
    {
        return new OrderId($this->uuid('6', $id));
    }

    private function invoiceId(string $id = '1'): SalesInvoiceId
    {
        return new SalesInvoiceId($this->uuid('7', $id));
    }

    private function creditId(string $id = '1'): SalesCreditInvoiceId
    {
        return new SalesCreditInvoiceId($this->uuid('8', $id));
    }

    private function administrationId(): AdministrationId
    {
        return new AdministrationId($this->uuid('9', '1'));
    }

    private function customerId(): CustomerId
    {
        return new CustomerId($this->uuid('a', '1'));
    }

    private function eur(): Currency
    {
        return new Currency('EUR');
    }

    private function uuid(string $prefix, string $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, (int) $sequence));
    }
}
