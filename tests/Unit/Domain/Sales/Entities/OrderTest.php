<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderNumber;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function test_constructor_exposes_immutable_state_without_source_quotation(): void
    {
        $order = $this->createOrder();

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $order->id()->toString());
        self::assertSame('ORD-001', $order->number()->value());
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $order->administrationId()->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440002', $order->customerId()->toString());
        self::assertSame('EUR', $order->currency()->code());
        self::assertSame('2026-07-15', $order->orderDate()->format('Y-m-d'));
        self::assertNull($order->sourceQuotationId());
        self::assertSame(OrderStatus::Draft, $order->status());
    }

    public function test_constructor_accepts_source_quotation(): void
    {
        $sourceQuotationId = new QuotationId(new Uuid('550e8400-e29b-41d4-a716-446655440003'));
        $order = $this->createOrder(sourceQuotationId: $sourceQuotationId);

        self::assertSame($sourceQuotationId, $order->sourceQuotationId());
    }

    /** @param list<string> $transitions */
    #[DataProvider('validTransitions')]
    public function test_valid_status_transitions(array $transitions, OrderStatus $expected): void
    {
        $order = $this->createOrder();

        foreach ($transitions as $transition) {
            $order->{$transition}();
        }

        self::assertSame($expected, $order->status());
    }

    /** @return iterable<string, array{list<string>, OrderStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'Draft to Confirmed' => [['confirm'], OrderStatus::Confirmed];
        yield 'Draft to Cancelled' => [['cancel'], OrderStatus::Cancelled];
        yield 'Confirmed to PartiallyInvoiced' => [['confirm', 'markPartiallyInvoiced'], OrderStatus::PartiallyInvoiced];
        yield 'Confirmed to FullyInvoiced' => [['confirm', 'markFullyInvoiced'], OrderStatus::FullyInvoiced];
        yield 'Confirmed to Cancelled' => [['confirm', 'cancel'], OrderStatus::Cancelled];
        yield 'PartiallyInvoiced to FullyInvoiced' => [['confirm', 'markPartiallyInvoiced', 'markFullyInvoiced'], OrderStatus::FullyInvoiced];
    }

    /** @param list<string> $transitions */
    #[DataProvider('invalidTransitions')]
    public function test_invalid_status_transitions_are_rejected(array $transitions): void
    {
        $order = $this->createOrder();

        $this->expectException(DomainException::class);

        foreach ($transitions as $transition) {
            $order->{$transition}();
        }
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidTransitions(): iterable
    {
        yield 'Draft to PartiallyInvoiced' => [['markPartiallyInvoiced']];
        yield 'Draft to FullyInvoiced' => [['markFullyInvoiced']];
        yield 'PartiallyInvoiced to Cancelled' => [['confirm', 'markPartiallyInvoiced', 'cancel']];
        yield 'PartiallyInvoiced to Confirmed' => [['confirm', 'markPartiallyInvoiced', 'confirm']];
    }

    #[DataProvider('terminalStatusTransitions')]
    public function test_terminal_statuses_reject_other_transitions(OrderStatus $status, string $transition): void
    {
        $order = $this->createOrder(status: $status);

        $this->expectException(DomainException::class);
        $order->{$transition}();
    }

    /** @return iterable<string, array{OrderStatus, string}> */
    public static function terminalStatusTransitions(): iterable
    {
        yield 'FullyInvoiced cannot confirm' => [OrderStatus::FullyInvoiced, 'confirm'];
        yield 'FullyInvoiced cannot cancel' => [OrderStatus::FullyInvoiced, 'cancel'];
        yield 'Cancelled cannot confirm' => [OrderStatus::Cancelled, 'confirm'];
        yield 'Cancelled cannot invoice' => [OrderStatus::Cancelled, 'markFullyInvoiced'];
    }

    /** @param list<string> $transitions */
    #[DataProvider('idempotentTransitions')]
    public function test_repeating_the_same_transition_is_idempotent(array $transitions, OrderStatus $expected): void
    {
        $order = $this->createOrder();

        foreach ($transitions as $transition) {
            $order->{$transition}();
        }

        self::assertSame($expected, $order->status());
    }

    /** @return iterable<string, array{list<string>, OrderStatus}> */
    public static function idempotentTransitions(): iterable
    {
        yield 'Confirmed' => [['confirm', 'confirm'], OrderStatus::Confirmed];
        yield 'PartiallyInvoiced' => [['confirm', 'markPartiallyInvoiced', 'markPartiallyInvoiced'], OrderStatus::PartiallyInvoiced];
        yield 'FullyInvoiced' => [['confirm', 'markFullyInvoiced', 'markFullyInvoiced'], OrderStatus::FullyInvoiced];
        yield 'Cancelled' => [['cancel', 'cancel'], OrderStatus::Cancelled];
    }

    public function test_identity_remains_unchanged_after_transition(): void
    {
        $order = $this->createOrder();
        $id = $order->id();

        $order->confirm();

        self::assertSame($id, $order->id());
    }

    private function createOrder(
        ?QuotationId $sourceQuotationId = null,
        OrderStatus $status = OrderStatus::Draft,
    ): Order {
        return new Order(
            new OrderId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new OrderNumber('ord-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            new DateTimeImmutable('2026-07-15'),
            $sourceQuotationId,
            $status,
        );
    }
}
