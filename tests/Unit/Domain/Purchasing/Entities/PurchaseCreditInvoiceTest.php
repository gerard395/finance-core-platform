<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PurchaseCreditInvoiceTest extends TestCase
{
    public function test_constructor_exposes_immutable_context_without_source_invoice(): void
    {
        $creditInvoice = $this->createCreditInvoice();

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $creditInvoice->id()->toString());
        self::assertSame('PCR-001', $creditInvoice->number()->value());
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $creditInvoice->administrationId()->toString());
        self::assertSame('550e8400-e29b-41d4-a716-446655440002', $creditInvoice->supplierId()->toString());
        self::assertSame('EUR', $creditInvoice->currency()->code());
        self::assertSame('2026-07-15', $creditInvoice->creditInvoiceDate()->format('Y-m-d'));
        self::assertNull($creditInvoice->sourcePurchaseInvoiceId());
        self::assertSame(PurchaseCreditInvoiceStatus::Draft, $creditInvoice->status());
    }

    public function test_constructor_accepts_source_purchase_invoice(): void
    {
        $sourceInvoiceId = new PurchaseInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440003'));
        $creditInvoice = $this->createCreditInvoice(sourcePurchaseInvoiceId: $sourceInvoiceId);

        self::assertSame($sourceInvoiceId, $creditInvoice->sourcePurchaseInvoiceId());
    }

    /** @param list<string> $transitions */
    #[DataProvider('validTransitions')]
    public function test_valid_status_transitions(array $transitions, PurchaseCreditInvoiceStatus $expected): void
    {
        $creditInvoice = $this->createCreditInvoice();

        foreach ($transitions as $transition) {
            $creditInvoice->{$transition}();
        }

        self::assertSame($expected, $creditInvoice->status());
    }

    /** @return iterable<string, array{list<string>, PurchaseCreditInvoiceStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'Draft to Finalized' => [['finalize'], PurchaseCreditInvoiceStatus::Finalized];
        yield 'Draft to Cancelled' => [['cancel'], PurchaseCreditInvoiceStatus::Cancelled];
        yield 'Finalized to Posted' => [['finalize', 'post'], PurchaseCreditInvoiceStatus::Posted];
        yield 'Finalized to Cancelled' => [['finalize', 'cancel'], PurchaseCreditInvoiceStatus::Cancelled];
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
        yield 'Cancelled to Finalized' => [['cancel', 'finalize']];
        yield 'Cancelled to Posted' => [['cancel', 'post']];
    }

    /** @param list<string> $transitions */
    #[DataProvider('idempotentTransitions')]
    public function test_repeating_the_same_transition_is_idempotent(array $transitions, PurchaseCreditInvoiceStatus $expected): void
    {
        $creditInvoice = $this->createCreditInvoice();

        foreach ($transitions as $transition) {
            $creditInvoice->{$transition}();
        }

        self::assertSame($expected, $creditInvoice->status());
    }

    /** @return iterable<string, array{list<string>, PurchaseCreditInvoiceStatus}> */
    public static function idempotentTransitions(): iterable
    {
        yield 'Finalized' => [['finalize', 'finalize'], PurchaseCreditInvoiceStatus::Finalized];
        yield 'Posted' => [['finalize', 'post', 'post'], PurchaseCreditInvoiceStatus::Posted];
        yield 'Cancelled' => [['cancel', 'cancel'], PurchaseCreditInvoiceStatus::Cancelled];
    }

    public function test_transitions_do_not_change_immutable_context(): void
    {
        $sourceInvoiceId = new PurchaseInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440003'));
        $creditInvoice = $this->createCreditInvoice(sourcePurchaseInvoiceId: $sourceInvoiceId);
        $context = [
            $creditInvoice->id(),
            $creditInvoice->number(),
            $creditInvoice->administrationId(),
            $creditInvoice->supplierId(),
            $creditInvoice->currency(),
            $creditInvoice->creditInvoiceDate(),
            $creditInvoice->sourcePurchaseInvoiceId(),
        ];

        $creditInvoice->finalize();

        self::assertSame($context, [
            $creditInvoice->id(),
            $creditInvoice->number(),
            $creditInvoice->administrationId(),
            $creditInvoice->supplierId(),
            $creditInvoice->currency(),
            $creditInvoice->creditInvoiceDate(),
            $creditInvoice->sourcePurchaseInvoiceId(),
        ]);
    }

    public function test_out_of_scope_apis_are_not_exposed(): void
    {
        $creditInvoice = $this->createCreditInvoice();

        self::assertFalse(method_exists($creditInvoice, 'lines'));
        self::assertFalse(method_exists($creditInvoice, 'tax'));
        self::assertFalse(method_exists($creditInvoice, 'postingRequest'));
        self::assertFalse(method_exists($creditInvoice, 'payments'));
        self::assertFalse(method_exists($creditInvoice, 'openItems'));
    }

    private function createCreditInvoice(
        ?PurchaseInvoiceId $sourcePurchaseInvoiceId = null,
        PurchaseCreditInvoiceStatus $status = PurchaseCreditInvoiceStatus::Draft,
    ): PurchaseCreditInvoice {
        return new PurchaseCreditInvoice(
            new PurchaseCreditInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new PurchaseCreditInvoiceNumber('pcr-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new SupplierId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            new DateTimeImmutable('2026-07-15'),
            $sourcePurchaseInvoiceId,
            $status,
        );
    }
}
