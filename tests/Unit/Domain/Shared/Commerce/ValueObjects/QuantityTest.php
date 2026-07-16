<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Commerce\ValueObjects;

use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_it_preserves_existing_normalization_and_value_semantics(): void
    {
        $quantity = new Quantity('2.500');

        self::assertSame('2.5', $quantity->value());
        self::assertSame('2.5', (string) $quantity);
        self::assertTrue($quantity->equals(new Quantity('2.5')));
        self::assertFalse($quantity->equals(new Quantity('3')));
    }

    #[DataProvider('invalidQuantities')]
    public function test_it_preserves_existing_validation(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Quantity($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidQuantities(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'float notation' => ['1e2'],
            'too precise' => ['1.123456789'],
        ];
    }
}
