<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuotationTest extends TestCase
{
    public function test_constructor_exposes_the_expected_immutable_state(): void
    {
        $quotation = $this->createQuotation();

        self::assertSame('QUO-001', $quotation->number()->value());
        self::assertSame('EUR', $quotation->currency()->code());
        self::assertSame(QuotationStatus::Draft, $quotation->status());
        self::assertSame('2026-07-15', $quotation->quotationDate()->format('Y-m-d'));
        self::assertSame('2026-08-15', $quotation->expiryDate()?->format('Y-m-d'));
    }

    public function test_valid_status_transitions_and_idempotence(): void
    {
        $accepted = $this->createQuotation();
        $accepted->send();
        $accepted->send();
        $accepted->accept();
        $accepted->accept();
        self::assertSame(QuotationStatus::Accepted, $accepted->status());

        $rejected = $this->createQuotation();
        $rejected->send();
        $rejected->reject();
        $rejected->reject();
        self::assertSame(QuotationStatus::Rejected, $rejected->status());

        $expired = $this->createQuotation();
        $expired->expire();
        $expired->expire();
        self::assertSame(QuotationStatus::Expired, $expired->status());
    }

    public function test_lines_are_managed_by_the_aggregate(): void
    {
        $quotation = $this->createQuotation(false);
        $first = $this->createLine('550e8400-e29b-41d4-a716-446655440010');
        $second = $this->createLine('550e8400-e29b-41d4-a716-446655440011');

        $quotation->addLine($first);
        $quotation->addLine($second);

        self::assertSame([$first, $second], $quotation->lines());
        self::assertTrue($quotation->hasLine($first->id()));
        self::assertSame($first, $quotation->line($first->id()));

        $quotation->removeLine($first->id());
        $quotation->removeLine($first->id());
        self::assertFalse($quotation->hasLine($first->id()));
    }

    public function test_duplicate_line_identity_is_rejected(): void
    {
        $quotation = $this->createQuotation(false);
        $line = $this->createLine();
        $quotation->addLine($line);

        $this->expectException(DomainException::class);
        $quotation->addLine($this->createLine());
    }

    public function test_quotation_without_lines_cannot_be_sent(): void
    {
        $this->expectException(DomainException::class);
        $this->createQuotation(false)->send();
    }

    public function test_lines_cannot_be_changed_after_sending(): void
    {
        $quotation = $this->createQuotation();
        $quotation->send();

        try {
            $quotation->addLine($this->createLine('550e8400-e29b-41d4-a716-446655440099'));
            self::fail('Expected adding a line after sending to be rejected.');
        } catch (DomainException) {
            self::assertCount(1, $quotation->lines());
        }

        $this->expectException(DomainException::class);
        $quotation->removeLine($quotation->lines()[0]->id());
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->createQuotation()->accept();
    }

    public function test_identity_and_number_remain_unchanged_after_transition(): void
    {
        $quotation = $this->createQuotation();
        $id = $quotation->id();
        $number = $quotation->number();

        $quotation->send();

        self::assertSame($id, $quotation->id());
        self::assertSame($number, $quotation->number());
    }

    public function test_expiry_date_before_quotation_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createQuotation(expiryDate: new DateTimeImmutable('2026-07-14'));
    }

    private function createQuotation(bool $withLine = true, ?DateTimeImmutable $expiryDate = null): Quotation
    {
        $quotation = new Quotation(
            new QuotationId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new QuotationNumber('quo-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            QuotationStatus::Draft,
            new DateTimeImmutable('2026-07-15'),
            $expiryDate ?? new DateTimeImmutable('2026-08-15'),
        );

        if ($withLine) {
            $quotation->addLine($this->createLine());
        }

        return $quotation;
    }

    private function createLine(string $uuid = '550e8400-e29b-41d4-a716-446655440010'): QuotationLine
    {
        return new QuotationLine(
            new QuotationLineId(new Uuid($uuid)),
            new LineDescription('Product delivery'),
            new Quantity('2'),
            new Money('12.50', new Currency('EUR')),
        );
    }
}
