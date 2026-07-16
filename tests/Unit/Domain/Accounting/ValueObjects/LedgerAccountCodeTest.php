<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LedgerAccountCodeTest extends TestCase
{
    public function test_it_accepts_a_valid_code_and_normalizes_lowercase_to_uppercase(): void
    {
        $code = new LedgerAccountCode('bank1000');

        self::assertSame('BANK1000', $code->value());
        self::assertSame('BANK1000', $code->toString());
        self::assertSame('BANK1000', (string) $code);
    }

    public function test_it_accepts_minimum_and_maximum_lengths(): void
    {
        self::assertSame('A1', new LedgerAccountCode('a1')->value());
        self::assertSame('A123456789012345', new LedgerAccountCode('a123456789012345')->value());
    }

    public function test_equality_uses_the_normalized_value(): void
    {
        self::assertTrue(new LedgerAccountCode('bank1000')->equals(new LedgerAccountCode('BANK1000')));
        self::assertFalse(new LedgerAccountCode('BANK1000')->equals(new LedgerAccountCode('BANK2000')));
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LedgerAccountCode($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'too long' => [str_repeat('A', 17)],
            'leading whitespace' => [' A1'],
            'trailing whitespace' => ['A1 '],
            'internal whitespace' => ['A 1'],
            'hyphen' => ['BANK-1000'],
            'underscore' => ['BANK_1000'],
            'unicode letter' => ['BÄNK'],
        ];
    }
}
