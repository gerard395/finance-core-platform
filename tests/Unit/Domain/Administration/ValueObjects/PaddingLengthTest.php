<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\PaddingLength;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaddingLengthTest extends TestCase
{
    public function test_it_accepts_zero_and_thirty_two(): void
    {
        self::assertSame(0, new PaddingLength(0)->value());
        self::assertSame(32, new PaddingLength(32)->value());
    }

    public function test_equal_lengths_compare_as_equal(): void
    {
        self::assertTrue(new PaddingLength(5)->equals(new PaddingLength(5)));
        self::assertFalse(new PaddingLength(5)->equals(new PaddingLength(6)));
    }

    public function test_it_rejects_a_negative_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaddingLength(-1);
    }

    public function test_it_rejects_a_length_above_thirty_two(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaddingLength(33);
    }
}
