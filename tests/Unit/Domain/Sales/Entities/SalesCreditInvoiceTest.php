<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesCreditInvoiceTest extends TestCase
{
    public function test_constructor_exposes_immutable_state_without_source_invoice(): void
    {
        $creditInvoice = $this->createCreditInvoice();

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $creditInvoice->id()->toString());
        self::assertSame('CRD-001', $creditInvoice->number()->value());
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $creditInvoice->administrationId()->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440002', $creditInvoice->customerId()->toString());
        self::assertSame('EUR', $creditInvoice->currency()->code());
        self::assertSame('2026-07-15', $creditInvoice->creditInvoiceDate()->format('Y-m-d'));
        self::assertNull($creditInvoice->sourceInvoiceId());
        self::assertSame(SalesCreditInvoiceStatus::Draft, $creditInvoice->status());
    }

    public function test_constructor_accepts_source_invoice(): void
    {
        $sourceInvoiceId = new SalesInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440003'));
        $creditInvoice = $this->createCreditInvoice(sourceInvoiceId: $sourceInvoiceId);

        self::assertSame($sourceInvoiceId, $creditInvoice->sourceInvoiceId());
    }

    /** @param list<string> $transitions */
    #[DataProvider('validTransitions')]
    public function test_valid_status_transitions(array $transitions, SalesCreditInvoiceStatus $expected): void
    {
        $creditInvoice = $this->createCreditInvoice();

        foreach ($transitions as $transition) {
            $creditInvoice->{$transition}();
        }

        self::assertSame($expected, $creditInvoice->status());
    }

    /** @return iterable<string, array{list<string>, SalesCreditInvoiceStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'Draft to Finalized' => [['finalize'], SalesCreditInvoiceStatus::Finalized];
        yield 'Draft to Cancelled' => [['cancel'], SalesCreditInvoiceStatus::Cancelled];
        yield 'Finalized to Posted' => [['finalize', 'post'], SalesCreditInvoiceStatus::Posted];
        yield 'Finalized to Cancelled' => [['finalize', 'cancel'], SalesCreditInvoiceStatus::Cancelled];
    }

    /** @param list<string> $transitions */
    #[DataProvider('invalidTransitions')]
    public function test_invalid_status_transitions_are_rejected(array $transitions): void
    {
        $creditInvoice = $this->createCreditInvoice();

        $this->expectException(DomainException::class);

        foreach ($transitions as $transition) {
            $creditInvoice->{$transition}();
        }
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidTransitions(): iterable
    {
        yield 'Draft to Posted' => [['post']];
        yield 'Posted to Cancelled' => [['finalize', 'post', 'cancel']];
        yield 'Posted to Finalized' => [['finalize', 'post', 'finalize']];
    }

    #[DataProvider('terminalStatusTransitions')]
    public function test_terminal_statuses_reject_other_transitions(SalesCreditInvoiceStatus $status, string $transition): void
    {
        $creditInvoice = $this->createCreditInvoice(status: $status);

        $this->expectException(DomainException::class);
        $creditInvoice->{$transition}();
    }

    /** @return iterable<string, array{SalesCreditInvoiceStatus, string}> */
    public static function terminalStatusTransitions(): iterable
    {
        yield 'Posted cannot cancel' => [SalesCreditInvoiceStatus::Posted, 'cancel'];
        yield 'Cancelled cannot finalize' => [SalesCreditInvoiceStatus::Cancelled, 'finalize'];
        yield 'Cancelled cannot post' => [SalesCreditInvoiceStatus::Cancelled, 'post'];
    }

    /** @param list<string> $transitions */
    #[DataProvider('idempotentTransitions')]
    public function test_repeating_the_same_transition_is_idempotent(array $transitions, SalesCreditInvoiceStatus $expected): void
    {
        $creditInvoice = $this->createCreditInvoice();

        foreach ($transitions as $transition) {
            $creditInvoice->{$transition}();
        }

        self::assertSame($expected, $creditInvoice->status());
    }

    /** @return iterable<string, array{list<string>, SalesCreditInvoiceStatus}> */
    public static function idempotentTransitions(): iterable
    {
        yield 'Finalized' => [['finalize', 'finalize'], SalesCreditInvoiceStatus::Finalized];
        yield 'Posted' => [['finalize', 'post', 'post'], SalesCreditInvoiceStatus::Posted];
        yield 'Cancelled' => [['cancel', 'cancel'], SalesCreditInvoiceStatus::Cancelled];
    }

    public function test_identity_remains_unchanged_after_transition(): void
    {
        $creditInvoice = $this->createCreditInvoice();
        $id = $creditInvoice->id();

        $creditInvoice->finalize();

        self::assertSame($id, $creditInvoice->id());
    }

    private function createCreditInvoice(
        ?SalesInvoiceId $sourceInvoiceId = null,
        SalesCreditInvoiceStatus $status = SalesCreditInvoiceStatus::Draft,
    ): SalesCreditInvoice {
        return new SalesCreditInvoice(
            new SalesCreditInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new SalesCreditInvoiceNumber('crd-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            new DateTimeImmutable('2026-07-15'),
            $sourceInvoiceId,
            $status,
        );
    }
}
