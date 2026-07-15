<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\DecimalPrecision;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DecimalPrecisionTest extends TestCase
{
    public function test_it_accepts_zero(): void
    {
        self::assertSame(0, new DecimalPrecision(0)->value());
    }

    public function test_it_accepts_two(): void
    {
        self::assertSame(2, new DecimalPrecision(2)->value());
    }

    public function test_it_accepts_eight(): void
    {
        self::assertSame(8, new DecimalPrecision(8)->value());
    }

    public function test_it_rejects_a_negative_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DecimalPrecision(-1);
    }

    public function test_it_rejects_nine(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DecimalPrecision(9);
    }

    public function test_equality_uses_the_precision_value(): void
    {
        $precision = new DecimalPrecision(2);

        self::assertTrue($precision->equals(new DecimalPrecision(2)));
        self::assertFalse($precision->equals(new DecimalPrecision(3)));
    }
}
