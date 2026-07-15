<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    public function test_it_accepts_and_compares_valid_phone_numbers(): void
    {
        $number = new PhoneNumber('+31 (0)20 123-4567');

        self::assertSame('+31 (0)20 123-4567', $number->value());
        self::assertTrue($number->equals(new PhoneNumber('+31 (0)20 123-4567')));
        self::assertFalse($number->equals(new PhoneNumber('+31 (0)20 765-4321')));
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_an_invalid_number(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhoneNumber($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'empty' => [''],
            'too short' => ['12'],
            'letters' => ['+31 CALL-NOW'],
            'leading whitespace' => [' +31 20 1234567'],
            'trailing whitespace' => ['+31 20 1234567 '],
        ];
    }
}
