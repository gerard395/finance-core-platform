<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Entities;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use PHPUnit\Framework\TestCase;

final class OpenItemTest extends TestCase
{
    public function test_it_is_constructed_with_all_values_exposed(): void
    {
        $item = $this->createOpenItem();

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $item->id()->toString());
        self::assertSame('123e4567-e89b-42d3-a456-426614174000', $item->administrationId()->toString());
        self::assertSame('936da01f-9abd-4d9d-80c7-02af85c822a8', $item->relationId()->toString());
        self::assertSame('6ba7b810-9dad-41d1-80b4-00c04fd430c8', $item->journalEntryId()->toString());
        self::assertSame('100', $item->originalAmount()->amount());
        self::assertSame('100', $item->openAmount()->amount());
        self::assertSame(OpenItemStatus::Open, $item->status());
        self::assertTrue($item->isOpen());
    }

    public function test_settlement_reduces_the_open_amount_exactly_without_floats(): void
    {
        $item = $this->createOpenItem('0.3');

        $item->settle($this->money('0.1'));

        self::assertSame('0.2', $item->openAmount()->amount());
        self::assertTrue($item->isPartiallySettled());
        self::assertSame('0.3', $item->originalAmount()->amount());
    }

    public function test_full_settlement_reaches_zero_and_can_then_be_closed(): void
    {
        $item = $this->createOpenItem();
        $item->settle($this->money('100'));

        self::assertTrue($item->openAmount()->isZero());
        self::assertTrue($item->isPartiallySettled());

        $item->close();
        $item->close();

        self::assertTrue($item->isClosed());
    }

    public function test_zero_settlement_is_idempotent(): void
    {
        $item = $this->createOpenItem();

        $item->settle($this->money('0'));
        $item->settle($this->money('0'));

        self::assertSame('100', $item->openAmount()->amount());
        self::assertSame(OpenItemStatus::Open, $item->status());
    }

    public function test_it_rejects_settlement_above_the_open_amount(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->settle($this->money('100.00000001'));
    }

    public function test_it_rejects_negative_settlement(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->settle($this->money('-1'));
    }

    public function test_it_rejects_a_different_settlement_currency(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->settle(new Money('1', new Currency('USD')));
    }

    public function test_it_cannot_close_while_an_amount_is_open(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->close();
    }

    public function test_closed_item_rejects_further_positive_settlement(): void
    {
        $item = $this->createOpenItem();
        $item->settle($this->money('100'));
        $item->close();

        $this->expectException(DomainException::class);
        $item->settle($this->money('1'));
    }

    public function test_constructor_rejects_invalid_amount_invariants(): void
    {
        $this->expectException(DomainException::class);

        $this->createOpenItem(originalAmount: '100', openAmount: '101');
    }

    private function createOpenItem(
        string $originalAmount = '100',
        ?string $openAmount = null,
        OpenItemStatus $status = OpenItemStatus::Open,
    ): OpenItem {
        return new OpenItem(
            new OpenItemId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new AdministrationId(new Uuid('123e4567-e89b-42d3-a456-426614174000')),
            new RelationId(new Uuid('936da01f-9abd-4d9d-80c7-02af85c822a8')),
            new JournalEntryId(new Uuid('6ba7b810-9dad-41d1-80b4-00c04fd430c8')),
            $this->money($originalAmount),
            $this->money($openAmount ?? $originalAmount),
            $status,
        );
    }

    private function money(string $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }
}
