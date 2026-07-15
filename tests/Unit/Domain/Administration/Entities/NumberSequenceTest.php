<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\Entities;

use App\Domain\Administration\Entities\NumberSequence;
use App\Domain\Administration\Enums\DocumentType;
use App\Domain\Administration\Enums\NumberSequenceResetPolicy;
use App\Domain\Administration\ValueObjects\NumberSequenceCode;
use App\Domain\Administration\ValueObjects\NumberSequenceId;
use App\Domain\Administration\ValueObjects\NumberSequenceName;
use App\Domain\Administration\ValueObjects\NumberSequencePrefix;
use App\Domain\Administration\ValueObjects\NumberSequenceSuffix;
use App\Domain\Administration\ValueObjects\PaddingLength;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use PHPUnit\Framework\TestCase;

final class NumberSequenceTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $sequence = $this->createSequence();

        self::assertInstanceOf(NumberSequenceId::class, $sequence->id());
        self::assertSame('SALES_INVOICE', $sequence->code()->value());
        self::assertSame('Sales invoices', $sequence->name()->value());
        self::assertSame(DocumentType::SalesInvoice, $sequence->documentType());
        self::assertSame(NumberSequenceResetPolicy::Never, $sequence->resetPolicy());
        self::assertTrue($sequence->isActive());
    }

    public function test_generate_number_uses_prefix_padding_and_suffix(): void
    {
        self::assertSame('INV-00042-NL', $this->createSequence()->generateNumber());
    }

    public function test_next_number_returns_and_increments_the_counter(): void
    {
        $sequence = $this->createSequence();

        self::assertSame(42, $sequence->nextNumber());
        self::assertSame(43, $sequence->peekNextNumber());
    }

    public function test_peek_next_number_does_not_increment_the_counter(): void
    {
        $sequence = $this->createSequence();

        self::assertSame(42, $sequence->peekNextNumber());
        self::assertSame(42, $sequence->peekNextNumber());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $sequence = $this->createSequence();

        $sequence->deactivate();
        $sequence->deactivate();
        self::assertFalse($sequence->isActive());

        $sequence->activate();
        $sequence->activate();
        self::assertTrue($sequence->isActive());
    }

    public function test_generate_number_consumes_one_number(): void
    {
        $sequence = $this->createSequence();

        $sequence->generateNumber();

        self::assertSame(43, $sequence->peekNextNumber());
    }

    public function test_inactive_sequence_rejects_number_generation_without_incrementing_counter(): void
    {
        $sequence = $this->createSequence();
        $sequence->deactivate();

        try {
            $sequence->generateNumber();
            self::fail('Expected inactive sequence generation to be rejected.');
        } catch (DomainException) {
            self::assertSame(42, $sequence->peekNextNumber());
        }
    }

    public function test_peek_next_number_remains_available_when_inactive(): void
    {
        $sequence = $this->createSequence();
        $sequence->deactivate();

        self::assertSame(42, $sequence->peekNextNumber());
    }

    public function test_sequence_can_generate_a_number_after_activation(): void
    {
        $sequence = $this->createSequence();
        $sequence->deactivate();
        $sequence->activate();

        self::assertSame('INV-00042-NL', $sequence->generateNumber());
    }

    public function test_prefix_and_suffix_are_exposed(): void
    {
        $sequence = $this->createSequence();

        self::assertSame('INV-', $sequence->prefix()->value());
        self::assertSame('-NL', $sequence->suffix()->value());
    }

    private function createSequence(): NumberSequence
    {
        return new NumberSequence(
            new NumberSequenceId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new NumberSequenceCode('sales_invoice'),
            new NumberSequenceName('Sales invoices'),
            DocumentType::SalesInvoice,
            new NumberSequencePrefix('INV-'),
            new NumberSequenceSuffix('-NL'),
            new PaddingLength(5),
            42,
            NumberSequenceResetPolicy::Never,
            true,
        );
    }
}
