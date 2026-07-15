<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\ContactName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactNameTest extends TestCase
{
    public function test_it_accepts_unicode_and_compares_by_value(): void
    {
        $name = new ContactName('Zoë Jansen');

        self::assertSame('Zoë Jansen', $name->value());
        self::assertTrue($name->equals(new ContactName('Zoë Jansen')));
        self::assertFalse($name->equals(new ContactName('zoë jansen')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_an_invalid_name(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContactName($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'leading whitespace' => [' Contact'],
            'trailing whitespace' => ['Contact '],
            'too long' => [str_repeat('A', 256)],
        ];
    }
}
