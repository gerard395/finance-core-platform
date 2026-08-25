<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\Entities;

use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\PurchaseInvoiceTestFactory;

final class PurchaseInvoiceTest extends TestCase
{
    public function test_draft_totals_finalize_actor_immutability_and_cancel_policy(): void
    {
        $line = PurchaseInvoiceTestFactory::line('cccccccc-0000-4000-8000-000000000001', '2', '50', '21');
        $invoice = PurchaseInvoiceTestFactory::invoice(lines: [$line]);
        self::assertSame('100', $invoice->netTotal()->amount());
        self::assertSame('21', $invoice->taxTotal()->amount());
        self::assertSame('121', $invoice->grossTotal()->amount());
        $actor = new UserId(new Uuid('cccccccc-0000-4000-8000-000000000002'));
        $at = new DateTimeImmutable('2026-08-25 12:00:00');
        self::assertTrue($invoice->finalize($actor, $at));
        self::assertFalse($invoice->finalize($actor, new DateTimeImmutable('2026-08-26')));
        self::assertSame($at, $invoice->finalizedAt());
        $this->expectException(DomainException::class);
        $invoice->addLine(PurchaseInvoiceTestFactory::line('cccccccc-0000-4000-8000-000000000003'));
    }

    public function test_draft_can_replace_header_and_lines_but_cancelled_is_immutable(): void
    {
        $invoice = PurchaseInvoiceTestFactory::invoice(lines: [PurchaseInvoiceTestFactory::line('cccccccc-0000-4000-8000-000000000004')]);
        $invoice->replaceDraft(new SupplierInvoiceNumber(' Ext-2 '), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-03'), null, new DateTimeImmutable('2026-08-31'), $invoice->documentAddress(), []);
        self::assertSame('Ext-2', $invoice->number()->value());
        self::assertSame('2026-08-03', $invoice->fiscalReportingDate()->format('Y-m-d'));
        self::assertTrue($invoice->cancel());
        self::assertFalse($invoice->cancel());
        self::assertSame(PurchaseInvoiceStatus::Cancelled, $invoice->status());
        $this->expectException(DomainException::class);
        $invoice->removeLine(PurchaseInvoiceTestFactory::line('cccccccc-0000-4000-8000-000000000004')->id());
    }

    public function test_posted_can_be_reconstituted_and_cannot_be_cancelled(): void
    {
        $invoice = PurchaseInvoiceTestFactory::invoice(status: PurchaseInvoiceStatus::Posted);
        self::assertSame(PurchaseInvoiceStatus::Posted, $invoice->status());
        $this->expectException(DomainException::class);
        $invoice->cancel();
    }

    public function test_due_date_before_invoice_date_is_rejected(): void
    {
        $invoice = PurchaseInvoiceTestFactory::invoice();
        $this->expectException(InvalidArgumentException::class);
        $invoice->replaceDraft(new SupplierInvoiceNumber('DATE-1'), new DateTimeImmutable('2026-08-20'), new DateTimeImmutable('2026-08-21'), null, new DateTimeImmutable('2026-08-19'), $invoice->documentAddress(), []);
    }
}
