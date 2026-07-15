<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\ValueObjects;

use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AddressValueObjectsTest extends TestCase
{
    public function test_address_id_wraps_uuid_and_compares_by_value(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new AddressId($uuid);

        self::assertSame($uuid, $id->uuid());
        self::assertTrue($id->equals(new AddressId(new Uuid($uuid->toString()))));
    }

    public function test_textual_value_objects_accept_valid_values(): void
    {
        self::assertSame('Street 1', new AddressLine('Street 1')->value());
        self::assertSame('1234 AB', new PostalCode('1234 AB')->value());
        self::assertSame('Den Haag', new City('Den Haag')->value());
        self::assertSame('NL', new CountryCode('nl')->value());
    }

    public function test_address_line_rejects_surrounding_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AddressLine(' Street 1');
    }

    public function test_postal_code_rejects_invalid_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PostalCode('12@AB');
    }

    public function test_city_rejects_an_empty_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new City('');
    }

    public function test_country_code_requires_two_ascii_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CountryCode('NLD');
    }
}
