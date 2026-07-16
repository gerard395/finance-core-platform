<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JournalEntryReferenceTest extends TestCase
{
    public function test_it_accepts_unicode_and_preserves_the_original_spelling(): void
    {
        $reference = new JournalEntryReference('Factuur 2026-ÉÉN');

        self::assertSame('Factuur 2026-ÉÉN', $reference->value());
        self::assertSame('Factuur 2026-ÉÉN', $reference->toString());
        self::assertSame('Factuur 2026-ÉÉN', (string) $reference);
    }

    public function test_it_accepts_minimum_and_maximum_lengths(): void
    {
        self::assertSame('A', new JournalEntryReference('A')->value());
        self::assertSame(str_repeat('Ä', 255), new JournalEntryReference(str_repeat('Ä', 255))->value());
    }

    public function test_equality_preserves_case(): void
    {
        self::assertTrue(new JournalEntryReference('Invoice 1')->equals(new JournalEntryReference('Invoice 1')));
        self::assertFalse(new JournalEntryReference('Invoice 1')->equals(new JournalEntryReference('invoice 1')));
    }

    #[DataProvider('invalidReferences')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JournalEntryReference($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidReferences(): array
    {
        return [
            'empty' => [''],
            'too long' => [str_repeat('A', 256)],
            'leading whitespace' => [' Invoice 1'],
            'trailing whitespace' => ['Invoice 1 '],
        ];
    }
}
