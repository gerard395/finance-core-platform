<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BankAccountValueObjectsTest extends TestCase
{
    public function test_valid_values_are_accepted_and_normalized(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame($uuid, new BankAccountId($uuid)->uuid());
        self::assertSame('NL91ABNA0417164300', new Iban('nl91abna0417164300')->value());
        self::assertSame('ABNANL2A', new Bic('abnanl2a')->value());
        self::assertSame('Main Account', new AccountName('Main Account')->value());
    }

    public function test_invalid_iban_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Iban('NL91 ABNA 0417');
    }

    public function test_invalid_bic_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Bic('INVALID');
    }

    public function test_invalid_account_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AccountName(' Account');
    }
}
