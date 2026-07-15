<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\SupplierNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupplierNumberTest extends TestCase
{
    public function test_it_normalizes_and_compares_valid_numbers(): void
    {
        $number = new SupplierNumber('supp-001');

        self::assertSame('SUPP-001', $number->value());
        self::assertSame('SUPP-001', $number->toString());
        self::assertSame('SUPP-001', (string) $number);
        self::assertTrue($number->equals(new SupplierNumber('SUPP-001')));
        self::assertFalse($number->equals(new SupplierNumber('SUPP-002')));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_an_invalid_number(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SupplierNumber($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'whitespace' => ['supp 001'],
            'symbol' => ['supp.001'],
            'too long' => [str_repeat('A', 33)],
        ];
    }
}
