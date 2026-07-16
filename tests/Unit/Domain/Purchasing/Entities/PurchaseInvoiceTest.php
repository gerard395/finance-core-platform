<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Entities\PurchaseInvoiceLine;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\SupplierReference;
use App\Domain\Relations\ValueObjects\SupplierId;
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

final class PurchaseInvoiceTest extends TestCase
{
    public function test_constructor_exposes_all_immutable_state(): void
    {
        $reference = new SupplierReference('SUP-REF-001');
        $invoice = $this->createInvoice(supplierReference: $reference);

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $invoice->id()->toString());
        self::assertSame('PINV-001', $invoice->number()->value());
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $invoice->administrationId()->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440002', $invoice->supplierId()->toString());
        self::assertSame('EUR', $invoice->currency()->code());
        self::assertSame('2026-07-15', $invoice->invoiceDate()->format('Y-m-d'));
        self::assertSame('2026-08-14', $invoice->dueDate()->format('Y-m-d'));
        self::assertSame($reference, $invoice->supplierReference());
        self::assertSame(PurchaseInvoiceStatus::Draft, $invoice->status());
    }

    public function test_supplier_reference_is_optional(): void
    {
        self::assertNull($this->createInvoice()->supplierReference());
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

        self::assertSame(PurchaseInvoiceStatus::Finalized, $invoice->status());
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
    public function test_valid_status_transitions(array $transitions, PurchaseInvoiceStatus $expected): void
    {
        $invoice = $this->createInvoice();

        foreach ($transitions as $transition) {
            $invoice->{$transition}();
        }

        self::assertSame($expected, $invoice->status());
    }

    /** @return iterable<string, array{list<string>, PurchaseInvoiceStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'Draft to Finalized' => [['finalize'], PurchaseInvoiceStatus::Finalized];
        yield 'Draft to Cancelled' => [['cancel'], PurchaseInvoiceStatus::Cancelled];
        yield 'Finalized to Posted' => [['finalize', 'post'], PurchaseInvoiceStatus::Posted];
        yield 'Finalized to Cancelled' => [['finalize', 'cancel'], PurchaseInvoiceStatus::Cancelled];
        yield 'Posted to Paid' => [['finalize', 'post', 'markAsPaid'], PurchaseInvoiceStatus::Paid];
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
        yield 'Paid to Cancelled' => [['finalize', 'post', 'markAsPaid', 'cancel']];
        yield 'Cancelled to Finalized' => [['cancel', 'finalize']];
    }

    /** @param list<string> $transitions */
    #[DataProvider('idempotentTransitions')]
    public function test_repeating_the_same_transition_is_idempotent(array $transitions, PurchaseInvoiceStatus $expected): void
    {
        $invoice = $this->createInvoice();

        foreach ($transitions as $transition) {
            $invoice->{$transition}();
        }

        self::assertSame($expected, $invoice->status());
    }

    /** @return iterable<string, array{list<string>, PurchaseInvoiceStatus}> */
    public static function idempotentTransitions(): iterable
    {
        yield 'Finalized' => [['finalize', 'finalize'], PurchaseInvoiceStatus::Finalized];
        yield 'Posted' => [['finalize', 'post', 'post'], PurchaseInvoiceStatus::Posted];
        yield 'Paid' => [['finalize', 'post', 'markAsPaid', 'markAsPaid'], PurchaseInvoiceStatus::Paid];
        yield 'Cancelled' => [['cancel', 'cancel'], PurchaseInvoiceStatus::Cancelled];
    }

    public function test_transitions_do_not_change_immutable_context(): void
    {
        $reference = new SupplierReference('SUP-REF-001');
        $invoice = $this->createInvoice(supplierReference: $reference);
        $context = [
            $invoice->id(),
            $invoice->number(),
            $invoice->administrationId(),
            $invoice->supplierId(),
            $invoice->currency(),
            $invoice->invoiceDate(),
            $invoice->dueDate(),
            $invoice->supplierReference(),
        ];

        $invoice->finalize();

        self::assertSame($context, [
            $invoice->id(),
            $invoice->number(),
            $invoice->administrationId(),
            $invoice->supplierId(),
            $invoice->currency(),
            $invoice->invoiceDate(),
            $invoice->dueDate(),
            $invoice->supplierReference(),
        ]);
    }

    private function createInvoice(
        PurchaseInvoiceStatus $status = PurchaseInvoiceStatus::Draft,
        ?SupplierReference $supplierReference = null,
        ?DateTimeImmutable $invoiceDate = null,
        ?DateTimeImmutable $dueDate = null,
        bool $withLine = true,
    ): PurchaseInvoice {
        $invoice = new PurchaseInvoice(
            new PurchaseInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new PurchaseInvoiceNumber('pinv-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new SupplierId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            $invoiceDate ?? new DateTimeImmutable('2026-07-15'),
            $dueDate ?? new DateTimeImmutable('2026-08-14'),
            $supplierReference,
            $status,
        );

        if ($withLine && $status === PurchaseInvoiceStatus::Draft) {
            $invoice->addLine($this->createLine());
        }

        return $invoice;
    }

    private function createLine(string $uuid = '550e8400-e29b-41d4-a716-446655440010'): PurchaseInvoiceLine
    {
        return new PurchaseInvoiceLine(
            new PurchaseInvoiceLineId(new Uuid($uuid)),
            new LineDescription('Purchased goods'),
            new Quantity('2'),
            new Money('12.50', new Currency('EUR')),
        );
    }
}
