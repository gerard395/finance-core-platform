<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Fiscal;

use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VatIdentificationNumberTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_it_normalizes_only_outer_whitespace_and_case(string $input, string $expected): void
    {
        self::assertSame($expected, (new VatIdentificationNumber($input))->toString());
    }

    public static function validValues(): array
    {
        return [
            [' nl123456789b01 ', 'NL123456789B01'],
            ['DE123456789', 'DE123456789'],
            ['FRXX123456789', 'FRXX123456789'],
            ['CHE-123.456.789', 'CHE-123.456.789'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_unsafe_syntax(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VatIdentificationNumber($value);
    }

    public static function invalidValues(): array
    {
        return [[''], ['N'], ['NL 123'], ['NL/123'], [str_repeat('A', 33)]];
    }
}
