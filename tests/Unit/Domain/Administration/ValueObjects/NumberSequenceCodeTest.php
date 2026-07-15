<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\NumberSequenceCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NumberSequenceCodeTest extends TestCase
{
    public function test_it_accepts_and_normalizes_a_valid_code(): void
    {
        self::assertSame('SALES_INVOICE', new NumberSequenceCode('sales_invoice')->value());
    }

    public function test_equal_codes_compare_as_equal(): void
    {
        self::assertTrue(new NumberSequenceCode('invoice')->equals(new NumberSequenceCode('INVOICE')));
    }

    public function test_string_representation_uses_the_normalized_value(): void
    {
        self::assertSame('INVOICE', (string) new NumberSequenceCode('invoice'));
    }

    public function test_it_rejects_an_invalid_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NumberSequenceCode('invoice code');
    }
}
