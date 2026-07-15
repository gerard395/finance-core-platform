<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\PermissionCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionCodeTest extends TestCase
{
    #[DataProvider('validCodes')]
    public function test_it_accepts_and_normalizes_valid_codes(string $input, string $expected): void
    {
        $code = new PermissionCode($input);

        self::assertSame($expected, $code->value());
        self::assertSame($expected, $code->toString());
        self::assertSame($expected, (string) $code);
    }

    /** @return array<string, array{string, string}> */
    public static function validCodes(): array
    {
        return [
            'administration view' => ['administration.view', 'ADMINISTRATION.VIEW'],
            'customer create' => ['CUSTOMER.CREATE', 'CUSTOMER.CREATE'],
            'journal post' => ['accounting.journal_post', 'ACCOUNTING.JOURNAL_POST'],
        ];
    }

    public function test_equality_uses_the_normalized_value(): void
    {
        self::assertTrue(new PermissionCode('customer.create')->equals(new PermissionCode('CUSTOMER.CREATE')));
        self::assertFalse(new PermissionCode('customer.create')->equals(new PermissionCode('customer.view')));
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_an_invalid_code(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PermissionCode($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'too short' => ['A'],
            'leading whitespace' => [' CUSTOMER.VIEW'],
            'trailing whitespace' => ['CUSTOMER.VIEW '],
            'hyphen' => ['CUSTOMER-CREATE'],
            'non ascii' => ['CUSTOMÉR.VIEW'],
            'too long' => [str_repeat('A', 65)],
        ];
    }
}
