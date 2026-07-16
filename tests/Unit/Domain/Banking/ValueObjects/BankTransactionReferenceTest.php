<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\ValueObjects;

use App\Domain\Banking\ValueObjects\BankTransactionReference;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BankTransactionReferenceTest extends TestCase
{
    public function test_it_preserves_unicode_and_uses_value_semantics(): void
    {
        $reference = new BankTransactionReference('Betaling-één');

        self::assertSame('Betaling-één', $reference->value());
        self::assertSame('Betaling-één', $reference->toString());
        self::assertSame('Betaling-één', (string) $reference);
        self::assertTrue($reference->equals(new BankTransactionReference('Betaling-één')));
        self::assertFalse($reference->equals(new BankTransactionReference('Betaling-twee')));
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BankTransactionReference($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidValues(): array
    {
        return [
            'empty' => [''],
            'leading whitespace' => [' REF'],
            'trailing whitespace' => ['REF '],
            'too long' => [str_repeat('R', 256)],
        ];
    }
}
