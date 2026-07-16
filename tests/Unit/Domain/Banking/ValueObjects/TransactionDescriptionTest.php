<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\ValueObjects;

use App\Domain\Banking\ValueObjects\TransactionDescription;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransactionDescriptionTest extends TestCase
{
    public function test_it_preserves_unicode_and_uses_value_semantics(): void
    {
        $description = new TransactionDescription('Ontvangen betaling één');

        self::assertSame('Ontvangen betaling één', $description->value());
        self::assertSame('Ontvangen betaling één', $description->toString());
        self::assertSame('Ontvangen betaling één', (string) $description);
        self::assertTrue($description->equals(new TransactionDescription('Ontvangen betaling één')));
        self::assertFalse($description->equals(new TransactionDescription('Ontvangen betaling twee')));
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TransactionDescription($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidValues(): array
    {
        return [
            'empty' => [''],
            'leading whitespace' => [' Description'],
            'trailing whitespace' => ['Description '],
            'too long' => [str_repeat('D', 1001)],
        ];
    }
}
