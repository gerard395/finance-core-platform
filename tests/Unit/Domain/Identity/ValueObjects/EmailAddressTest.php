<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function test_it_accepts_and_normalizes_a_valid_email_address(): void
    {
        $emailAddress = new EmailAddress('User@Example.COM');

        self::assertSame('user@example.com', $emailAddress->value());
        self::assertSame('user@example.com', $emailAddress->toString());
        self::assertSame('user@example.com', (string) $emailAddress);
    }

    public function test_normalized_email_addresses_compare_as_equal(): void
    {
        self::assertTrue(
            new EmailAddress('User@Example.com')->equals(new EmailAddress('user@example.COM')),
        );
    }

    #[DataProvider('invalidEmailAddresses')]
    public function test_it_rejects_an_invalid_email_address(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailAddress($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidEmailAddresses(): array
    {
        return [
            'empty' => [''],
            'missing at sign' => ['user.example.com'],
            'missing local part' => ['@example.com'],
            'surrounding whitespace' => [' user@example.com '],
        ];
    }
}
