<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxCodeNameTest extends TestCase
{
    public function test_it_preserves_unicode_and_uses_value_object_semantics(): void
    {
        $name = new TaxCodeName('Algemeen tarief België');

        self::assertSame('Algemeen tarief België', $name->value());
        self::assertSame('Algemeen tarief België', $name->toString());
        self::assertSame('Algemeen tarief België', (string) $name);
        self::assertTrue($name->equals(new TaxCodeName('Algemeen tarief België')));
        self::assertFalse($name->equals(new TaxCodeName('Verlaagd tarief')));
    }

    #[DataProvider('invalidNames')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TaxCodeName($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'leading whitespace' => [' Tax rate'],
            'trailing whitespace' => ['Tax rate '],
            'too long' => [str_repeat('A', 256)],
        ];
    }
}
