<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesCreditInvoiceLine;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\LineDescription;
use App\Domain\Sales\ValueObjects\Quantity;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
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

    public function test_lines_are_owned_and_managed_by_the_aggregate(): void
    {
        $creditInvoice = $this->createCreditInvoice(withLine: false);
        $first = $this->createLine('550e8400-e29b-41d4-a716-446655440010');
        $second = $this->createLine('550e8400-e29b-41d4-a716-446655440011');

        $creditInvoice->addLine($first);
        $creditInvoice->addLine($second);

        self::assertSame([$first, $second], $creditInvoice->lines());
        self::assertTrue($creditInvoice->hasLine($first->id()));
        self::assertSame($first, $creditInvoice->line($first->id()));

        $creditInvoice->removeLine($first->id());
        $creditInvoice->removeLine($first->id());

        self::assertFalse($creditInvoice->hasLine($first->id()));
        self::assertNull($creditInvoice->line($first->id()));
    }

    public function test_duplicate_line_identity_is_rejected(): void
    {
        $creditInvoice = $this->createCreditInvoice();

        $this->expectException(DomainException::class);
        $creditInvoice->addLine($this->createLine());
    }

    public function test_credit_invoice_without_lines_cannot_be_finalized(): void
    {
        $this->expectException(DomainException::class);
        $this->createCreditInvoice(withLine: false)->finalize();
    }

    public function test_credit_invoice_with_a_line_can_be_finalized(): void
    {
        $creditInvoice = $this->createCreditInvoice();

        $creditInvoice->finalize();

        self::assertSame(SalesCreditInvoiceStatus::Finalized, $creditInvoice->status());
    }

    public function test_lines_cannot_be_changed_after_finalization(): void
    {
        $creditInvoice = $this->createCreditInvoice();
        $creditInvoice->finalize();

        try {
            $creditInvoice->addLine($this->createLine('550e8400-e29b-41d4-a716-446655440099'));
            self::fail('Expected adding a line after finalization to be rejected.');
        } catch (DomainException) {
            self::assertCount(1, $creditInvoice->lines());
        }

        $this->expectException(DomainException::class);
        $creditInvoice->removeLine($creditInvoice->lines()[0]->id());
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
        bool $withLine = true,
    ): SalesCreditInvoice {
        $creditInvoice = new SalesCreditInvoice(
            new SalesCreditInvoiceId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new SalesCreditInvoiceNumber('crd-001'),
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new Currency('EUR'),
            new DateTimeImmutable('2026-07-15'),
            $sourceInvoiceId,
            $status,
        );

        if ($withLine && $status === SalesCreditInvoiceStatus::Draft) {
            $creditInvoice->addLine($this->createLine());
        }

        return $creditInvoice;
    }

    private function createLine(string $uuid = '550e8400-e29b-41d4-a716-446655440010'): SalesCreditInvoiceLine
    {
        return new SalesCreditInvoiceLine(
            new SalesCreditInvoiceLineId(new Uuid($uuid)),
            new LineDescription('Product delivery'),
            new Quantity('2'),
            new Money('12.50', new Currency('EUR')),
        );
    }
}
