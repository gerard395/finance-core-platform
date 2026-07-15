<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\RoleName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoleNameTest extends TestCase
{
    public function test_it_accepts_unicode_and_exposes_its_value(): void
    {
        $name = new RoleName('Financiële beheerder');

        self::assertSame('Financiële beheerder', $name->value());
        self::assertSame('Financiële beheerder', $name->toString());
        self::assertSame('Financiële beheerder', (string) $name);
    }

    public function test_equality_is_case_sensitive(): void
    {
        $name = new RoleName('Administrator');

        self::assertTrue($name->equals(new RoleName('Administrator')));
        self::assertFalse($name->equals(new RoleName('administrator')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_an_invalid_name(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RoleName($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'leading whitespace' => [' Administrator'],
            'trailing whitespace' => ['Administrator '],
            'too long' => [str_repeat('A', 256)],
        ];
    }
}
