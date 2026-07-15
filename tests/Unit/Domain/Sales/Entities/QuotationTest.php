<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Shared\Finance\Currency;
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
        $this->createQuotation(new DateTimeImmutable('2026-07-14'));
    }

    private function createQuotation(?DateTimeImmutable $expiryDate = null): Quotation
    {
        return new Quotation(
            new QuotationId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new QuotationNumber('quo-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            QuotationStatus::Draft,
            new DateTimeImmutable('2026-07-15'),
            $expiryDate ?? new DateTimeImmutable('2026-08-15'),
        );
    }
}
