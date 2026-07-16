<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Commerce\ValueObjects;

use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LineDescriptionTest extends TestCase
{
    public function test_it_preserves_existing_unicode_and_value_semantics(): void
    {
        $description = new LineDescription('Consulting België');

        self::assertSame('Consulting België', $description->value());
        self::assertSame('Consulting België', (string) $description);
        self::assertTrue($description->equals(new LineDescription('Consulting België')));
        self::assertFalse($description->equals(new LineDescription('Consulting Nederland')));
    }

    #[DataProvider('invalidDescriptions')]
    public function test_it_preserves_existing_validation(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LineDescription($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidDescriptions(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'leading whitespace' => [' Consulting'],
            'trailing whitespace' => ['Consulting '],
            'too long' => [str_repeat('A', 1001)],
        ];
    }
}
