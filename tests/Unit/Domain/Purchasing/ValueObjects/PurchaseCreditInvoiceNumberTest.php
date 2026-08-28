<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\ValueObjects;

use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PurchaseCreditInvoiceNumberTest extends TestCase
{
    public function test_it_preserves_case_and_exposes_a_valid_external_number(): void
    {
        $number = new PurchaseCreditInvoiceNumber('pcr-001');

        self::assertSame('pcr-001', $number->value());
        self::assertSame('pcr-001', $number->toString());
        self::assertSame('pcr-001', (string) $number);
        self::assertFalse($number->equals(new PurchaseCreditInvoiceNumber('PCR-001')));
        self::assertFalse($number->equals(new PurchaseCreditInvoiceNumber('PCR-002')));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseCreditInvoiceNumber($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'too long' => [str_repeat('A', 129)],
            'control character' => ["PCR\n001"],
        ];
    }
}
