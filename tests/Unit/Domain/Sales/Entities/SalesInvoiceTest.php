<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesInvoiceTest extends TestCase
{
    public function test_constructor_exposes_immutable_state_without_source_order(): void
    {
        $invoice = $this->createInvoice();

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $invoice->id()->toString());
        self::assertSame('INV-001', $invoice->number()->value());
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $invoice->administrationId()->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440002', $invoice->customerId()->toString());
        self::assertSame('EUR', $invoice->currency()->code());
        self::assertSame('2026-07-15', $invoice->invoiceDate()->format('Y-m-d'));
        self::assertSame('2026-08-14', $invoice->dueDate()->format('Y-m-d'));
        self::assertNull($invoice->sourceOrderId());
        self::assertSame(SalesInvoiceStatus::Draft, $invoice->status());
    }

    public function test_constructor_accepts_source_order(): void
    {
        $sourceOrderId = new OrderId(new Uuid('550e8400-e29b-41d4-a716-446655440003'));
        $invoice = $this->createInvoice(sourceOrderId: $sourceOrderId);

        self::assertSame($sourceOrderId, $invoice->sourceOrderId());
    }

    public function test_due_date_may_equal_invoice_date(): void
    {
        $date = new DateTimeImmutable('2026-07-15');
        $invoice = $this->createInvoice(invoiceDate: $date, dueDate: $date);

        self::assertSame($date, $invoice->invoiceDate());
        self::assertSame($date, $invoice->dueDate());
    }

    public function test_due_date_before_invoice_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createInvoice(dueDate: new DateTimeImmutable('2026-07-14'));
    }

    public function test_lines_are_owned_and_managed_by_the_aggregate(): void
    {
        $invoice = $this->createInvoice(withLine: false);
        $first = $this->createLine('550e8400-e29b-41d4-a716-446655440010');
        $second = $this->createLine('550e8400-e29b-41d4-a716-446655440011');

        $invoice->addLine($first);
        $invoice->addLine($second);

        self::assertSame([$first, $second], $invoice->lines());
        self::assertTrue($invoice->hasLine($first->id()));
        self::assertSame($first, $invoice->line($first->id()));

        $invoice->removeLine($first->id());
        $invoice->removeLine($first->id());

        self::assertFalse($invoice->hasLine($first->id()));
        self::assertNull($invoice->line($first->id()));
    }

    public function test_duplicate_line_identity_is_rejected(): void
    {
        $invoice = $this->createInvoice();

        $this->expectException(DomainException::class);
        $invoice->addLine($this->createLine());
    }

    public function test_invoice_without_lines_cannot_be_finalized(): void
    {
        $this->expectException(DomainException::class);
        $this->createInvoice(withLine: false)->finalize();
    }

    public function test_invoice_with_a_line_can_be_finalized(): void
    {
        $invoice = $this->createInvoice();

        $invoice->finalize();

        self::assertSame(SalesInvoiceStatus::Finalized, $invoice->status());
    }

    public function test_lines_cannot_be_changed_after_finalization(): void
    {
        $invoice = $this->createInvoice();
        $invoice->finalize();

        try {
            $invoice->addLine($this->createLine('550e8400-e29b-41d4-a716-446655440099'));
            self::fail('Expected adding a line after finalization to be rejected.');
        } catch (DomainException) {
            self::assertCount(1, $invoice->lines());
        }

        $this->expectException(DomainException::class);
        $invoice->removeLine($invoice->lines()[0]->id());
    }

    /** @param list<string> $transitions */
    #[DataProvider('validTransitions')]
    public function test_valid_status_transitions(array $transitions, SalesInvoiceStatus $expected): void
    {
        $invoice = $this->createInvoice();

        foreach ($transitions as $transition) {
            $invoice->{$transition}();
        }

        self::assertSame($expected, $invoice->status());
    }

    /** @return iterable<string, array{list<string>, SalesInvoiceStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'Draft to Finalized' => [['finalize'], SalesInvoiceStatus::Finalized];
        yield 'Draft to Cancelled' => [['cancel'], SalesInvoiceStatus::Cancelled];
        yield 'Finalized to Posted' => [['finalize', 'post'], SalesInvoiceStatus::Posted];
        yield 'Finalized to Cancelled' => [['finalize', 'cancel'], SalesInvoiceStatus::Cancelled];
        yield 'Posted to Paid' => [['finalize', 'post', 'markAsPaid'], SalesInvoiceStatus::Paid];
    }

    /** @param list<string> $transitions */
    #[DataProvider('invalidTransitions')]
    public function test_invalid_status_transitions_are_rejected(array $transitions): void
    {
        $invoice = $this->createInvoice();

        $this->expectException(DomainException::class);

        foreach ($transitions as $transition) {
            $invoice->{$transition}();
        }
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidTransitions(): iterable
    {
        yield 'Draft to Posted' => [['post']];
        yield 'Draft to Paid' => [['markAsPaid']];
        yield 'Finalized to Paid' => [['finalize', 'markAsPaid']];
        yield 'Posted to Cancelled' => [['finalize', 'post', 'cancel']];
        yield 'Posted to Finalized' => [['finalize', 'post', 'finalize']];
    }

    #[DataProvider('terminalStatusTransitions')]
    public function test_terminal_statuses_reject_other_transitions(SalesInvoiceStatus $status, string $transition): void
    {
        $invoice = $this->createInvoice(status: $status);

        $this->expectException(DomainException::class);
        $invoice->{$transition}();
    }

    /** @return iterable<string, array{SalesInvoiceStatus, string}> */
    public static function terminalStatusTransitions(): iterable
    {
        yield 'Paid cannot finalize' => [SalesInvoiceStatus::Paid, 'finalize'];
        yield 'Paid cannot cancel' => [SalesInvoiceStatus::Paid, 'cancel'];
        yield 'Cancelled cannot finalize' => [SalesInvoiceStatus::Cancelled, 'finalize'];
        yield 'Cancelled cannot post' => [SalesInvoiceStatus::Cancelled, 'post'];
    }

    /** @param list<string> $transitions */
    #[DataProvider('idempotentTransitions')]
    public function test_repeating_the_same_transition_is_idempotent(array $transitions, SalesInvoiceStatus $expected): void
    {
        $invoice = $this->createInvoice();

        foreach ($transitions as $transition) {
            $invoice->{$transition}();
        }

        self::assertSame($expected, $invoice->status());
    }

    /** @return iterable<string, array{list<string>, SalesInvoiceStatus}> */
    public static function idempotentTransitions(): iterable
    {
        yield 'Finalized' => [['finalize', 'finalize'], SalesInvoiceStatus::Finalized];
        yield 'Posted' => [['finalize', 'post', 'post'], SalesInvoiceStatus::Posted];
        yield 'Paid' => [['finalize', 'post', 'markAsPaid', 'markAsPaid'], SalesInvoiceStatus::Paid];
        yield 'Cancelled' => [['cancel', 'cancel'], SalesInvoiceStatus::Cancelled];
    }

    public function test_identity_remains_unchanged_after_transition(): void
    {
        $invoice = $this->createInvoice();
        $id = $invoice->id();

        $invoice->finalize();

        self::assertSame($id, $invoice->id());
    }

    private function createInvoice(
        ?OrderId $sourceOrderId = null,
        SalesInvoiceStatus $status = SalesInvoiceStatus::Draft,
        ?DateTimeImmutable $invoiceDate = null,
        ?DateTimeImmutable $dueDate = null,
        bool $withLine = true,
    ): SalesInvoice {
        $invoice = new SalesInvoice(
            new SalesInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new SalesInvoiceNumber('inv-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            $invoiceDate ?? new DateTimeImmutable('2026-07-15'),
            $dueDate ?? new DateTimeImmutable('2026-08-14'),
            $sourceOrderId,
            $status,
        );

        if ($withLine && $status === SalesInvoiceStatus::Draft) {
            $invoice->addLine($this->createLine());
        }

        return $invoice;
    }

    private function createLine(string $uuid = '550e8400-e29b-41d4-a716-446655440010'): SalesInvoiceLine
    {
        return new SalesInvoiceLine(
            new SalesInvoiceLineId(new Uuid($uuid)),
            new LineDescription('Product delivery'),
            new Quantity('2'),
            new Money('12.50', new Currency('EUR')),
        );
    }
}
