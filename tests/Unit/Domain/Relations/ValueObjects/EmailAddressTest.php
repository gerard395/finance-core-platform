<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function test_it_accepts_normalizes_and_compares_valid_addresses(): void
    {
        $email = new EmailAddress('Contact@Example.COM');

        self::assertSame('contact@example.com', $email->value());
        self::assertTrue($email->equals(new EmailAddress('contact@EXAMPLE.com')));
        self::assertFalse($email->equals(new EmailAddress('other@example.com')));
    }

    public function test_it_rejects_an_invalid_address(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailAddress('invalid-address');
    }
}
