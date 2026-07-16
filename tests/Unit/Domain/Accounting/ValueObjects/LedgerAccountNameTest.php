<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LedgerAccountNameTest extends TestCase
{
    public function test_it_accepts_unicode_and_preserves_the_original_spelling(): void
    {
        $name = new LedgerAccountName('Liquide middelen €');

        self::assertSame('Liquide middelen €', $name->value());
        self::assertSame('Liquide middelen €', $name->toString());
        self::assertSame('Liquide middelen €', (string) $name);
    }

    public function test_it_accepts_minimum_and_maximum_lengths(): void
    {
        self::assertSame('Kas', new LedgerAccountName('Kas')->value());
        self::assertSame(str_repeat('Ä', 255), new LedgerAccountName(str_repeat('Ä', 255))->value());
    }

    public function test_equality_preserves_case(): void
    {
        self::assertTrue(new LedgerAccountName('Bank')->equals(new LedgerAccountName('Bank')));
        self::assertFalse(new LedgerAccountName('Bank')->equals(new LedgerAccountName('bank')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LedgerAccountName($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'too long' => [str_repeat('A', 256)],
            'leading whitespace' => [' Bank'],
            'trailing whitespace' => ['Bank '],
        ];
    }
}
