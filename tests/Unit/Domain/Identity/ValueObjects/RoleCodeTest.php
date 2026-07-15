<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\RoleCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoleCodeTest extends TestCase
{
    public function test_it_normalizes_a_valid_code_to_uppercase(): void
    {
        $code = new RoleCode('finance_admin');

        self::assertSame('FINANCE_ADMIN', $code->value());
        self::assertSame('FINANCE_ADMIN', $code->toString());
        self::assertSame('FINANCE_ADMIN', (string) $code);
    }

    public function test_equality_uses_the_normalized_value(): void
    {
        self::assertTrue(new RoleCode('finance_admin')->equals(new RoleCode('FINANCE_ADMIN')));
        self::assertFalse(new RoleCode('finance_admin')->equals(new RoleCode('auditor')));
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_an_invalid_code(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RoleCode($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'whitespace' => ['finance admin'],
            'invalid symbol' => ['finance.admin'],
            'too long' => [str_repeat('A', 33)],
        ];
    }
}
