<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\PermissionName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionNameTest extends TestCase
{
    public function test_it_accepts_unicode_and_exposes_its_value(): void
    {
        $name = new PermissionName('Financiële rapportage bekijken');

        self::assertSame('Financiële rapportage bekijken', $name->value());
        self::assertSame('Financiële rapportage bekijken', $name->toString());
        self::assertSame('Financiële rapportage bekijken', (string) $name);
    }

    public function test_equality_is_case_sensitive(): void
    {
        $name = new PermissionName('View customers');

        self::assertTrue($name->equals(new PermissionName('View customers')));
        self::assertFalse($name->equals(new PermissionName('view customers')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_an_invalid_name(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PermissionName($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'leading whitespace' => [' View customers'],
            'trailing whitespace' => ['View customers '],
            'too long' => [str_repeat('A', 256)],
        ];
    }
}
