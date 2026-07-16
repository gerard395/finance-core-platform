<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\JournalName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JournalNameTest extends TestCase
{
    public function test_it_accepts_unicode_and_uses_value_object_semantics(): void
    {
        $name = new JournalName('Verkoop Nederland €');

        self::assertSame('Verkoop Nederland €', $name->value());
        self::assertSame('Verkoop Nederland €', $name->toString());
        self::assertSame('Verkoop Nederland €', (string) $name);
        self::assertTrue($name->equals(new JournalName('Verkoop Nederland €')));
        self::assertFalse($name->equals(new JournalName('Verkoop buitenland')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JournalName($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'too long' => [str_repeat('A', 256)],
            'leading whitespace' => [' Verkoop'],
            'trailing whitespace' => ['Verkoop '],
        ];
    }
}
