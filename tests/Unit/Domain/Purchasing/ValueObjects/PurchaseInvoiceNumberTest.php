<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\ValueObjects;

use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PurchaseInvoiceNumberTest extends TestCase
{
    public function test_it_normalizes_and_exposes_a_valid_number(): void
    {
        $number = new PurchaseInvoiceNumber('pinv-001');

        self::assertSame('PINV-001', $number->value());
        self::assertSame('PINV-001', $number->toString());
        self::assertSame('PINV-001', (string) $number);
        self::assertTrue($number->equals(new PurchaseInvoiceNumber('PINV-001')));
        self::assertFalse($number->equals(new PurchaseInvoiceNumber('PINV-002')));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseInvoiceNumber($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'too long' => [str_repeat('A', 33)],
            'whitespace' => ['PINV 001'],
            'invalid symbol' => ['PINV.001'],
        ];
    }
}
