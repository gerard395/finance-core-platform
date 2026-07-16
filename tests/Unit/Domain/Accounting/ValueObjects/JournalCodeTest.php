<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\JournalCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JournalCodeTest extends TestCase
{
    public function test_it_normalizes_a_valid_code_and_uses_value_object_semantics(): void
    {
        $code = new JournalCode('vrk01');

        self::assertSame('VRK01', $code->value());
        self::assertSame('VRK01', $code->toString());
        self::assertSame('VRK01', (string) $code);
        self::assertTrue($code->equals(new JournalCode('VRK01')));
        self::assertFalse($code->equals(new JournalCode('INK01')));
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JournalCode($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'too long' => [str_repeat('A', 17)],
            'leading whitespace' => [' VRK'],
            'trailing whitespace' => ['VRK '],
            'invalid symbol' => ['VRK-01'],
            'unicode letter' => ['VËRK'],
        ];
    }
}
