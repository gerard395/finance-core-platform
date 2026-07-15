<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\RelationCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RelationCodeTest extends TestCase
{
    public function test_it_normalizes_and_compares_valid_codes(): void
    {
        $code = new RelationCode('relation_001');

        self::assertSame('RELATION_001', $code->value());
        self::assertTrue($code->equals(new RelationCode('RELATION_001')));
        self::assertFalse($code->equals(new RelationCode('RELATION_002')));
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_an_invalid_code(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RelationCode($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'whitespace' => ['relation 001'],
            'symbol' => ['relation.001'],
            'too long' => [str_repeat('A', 33)],
        ];
    }
}
