<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\DisplayName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DisplayNameTest extends TestCase
{
    public function test_it_accepts_unicode_and_compares_by_value(): void
    {
        $name = new DisplayName('Financiële Relatie');

        self::assertSame('Financiële Relatie', $name->value());
        self::assertTrue($name->equals(new DisplayName('Financiële Relatie')));
        self::assertFalse($name->equals(new DisplayName('financiële relatie')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_an_invalid_name(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DisplayName($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'leading whitespace' => [' Relation'],
            'trailing whitespace' => ['Relation '],
            'too long' => [str_repeat('A', 256)],
        ];
    }
}
