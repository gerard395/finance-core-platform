<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\AdministrationCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdministrationCodeTest extends TestCase
{
    public function test_it_accepts_a_valid_code_and_normalizes_it(): void
    {
        self::assertSame('FINANCE_01', new AdministrationCode('finance_01')->value());
    }

    public function test_it_accepts_minimum_and_maximum_length(): void
    {
        self::assertSame('A1', new AdministrationCode('a1')->value());
        self::assertSame(str_repeat('A', 32), new AdministrationCode(str_repeat('a', 32))->value());
    }

    public function test_equality_and_string_representations_use_the_normalized_value(): void
    {
        $code = new AdministrationCode('finance-01');

        self::assertTrue($code->equals(new AdministrationCode('FINANCE-01')));
        self::assertFalse($code->equals(new AdministrationCode('FINANCE-02')));
        self::assertSame('FINANCE-01', $code->toString());
        self::assertSame('FINANCE-01', (string) $code);
    }

    public function test_it_rejects_invalid_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationCode('FINANCE.01');
    }

    public function test_it_rejects_leading_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationCode(' FINANCE');
    }

    public function test_it_rejects_trailing_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationCode('FINANCE ');
    }

    public function test_it_rejects_a_too_short_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationCode('A');
    }

    public function test_it_rejects_a_too_long_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationCode(str_repeat('A', 33));
    }
}
