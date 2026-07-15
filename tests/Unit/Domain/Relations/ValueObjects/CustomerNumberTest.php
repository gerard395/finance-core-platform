<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\CustomerNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerNumberTest extends TestCase
{
    public function test_it_normalizes_and_compares_valid_numbers(): void
    {
        $number = new CustomerNumber('cust-001');

        self::assertSame('CUST-001', $number->value());
        self::assertSame('CUST-001', $number->toString());
        self::assertSame('CUST-001', (string) $number);
        self::assertTrue($number->equals(new CustomerNumber('CUST-001')));
        self::assertFalse($number->equals(new CustomerNumber('CUST-002')));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_an_invalid_number(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CustomerNumber($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'whitespace' => ['cust 001'],
            'symbol' => ['cust.001'],
            'too long' => [str_repeat('A', 33)],
        ];
    }
}
