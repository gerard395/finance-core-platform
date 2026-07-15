<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\DisplayName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DisplayNameTest extends TestCase
{
    public function test_it_accepts_unicode_and_exposes_its_value(): void
    {
        $name = new DisplayName('Zoë 日本');

        self::assertSame('Zoë 日本', $name->value());
        self::assertSame('Zoë 日本', $name->toString());
        self::assertSame('Zoë 日本', (string) $name);
    }

    public function test_equality_is_case_sensitive(): void
    {
        $name = new DisplayName('Finance User');

        self::assertTrue($name->equals(new DisplayName('Finance User')));
        self::assertFalse($name->equals(new DisplayName('finance user')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_invalid_names(string $value): void
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
            'leading whitespace' => [' Finance'],
            'trailing whitespace' => ['Finance '],
            'too long' => [str_repeat('A', 256)],
        ];
    }
}
