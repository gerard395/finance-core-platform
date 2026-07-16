<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxCodeCodeTest extends TestCase
{
    public function test_it_normalizes_and_uses_value_object_semantics(): void
    {
        $code = new TaxCodeCode('vat21');

        self::assertSame('VAT21', $code->value());
        self::assertSame('VAT21', $code->toString());
        self::assertSame('VAT21', (string) $code);
        self::assertTrue($code->equals(new TaxCodeCode('VAT21')));
        self::assertFalse($code->equals(new TaxCodeCode('VAT09')));
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaxCodeCode($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'too long' => [str_repeat('A', 17)],
            'whitespace' => ['VAT 21'],
            'symbol' => ['VAT-21'],
        ];
    }
}
