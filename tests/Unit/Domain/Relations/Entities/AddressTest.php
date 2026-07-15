<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Address;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $address = $this->createAddress();

        self::assertSame(AddressType::Visiting, $address->type());
        self::assertSame('Finance Street 1', $address->addressLine()->value());
        self::assertNull($address->addressLine2());
        self::assertSame('1234 AB', $address->postalCode()->value());
        self::assertSame('Amsterdam', $address->city()->value());
        self::assertSame('NL', $address->countryCode()->value());
        self::assertTrue($address->isActive());
    }

    public function test_activate_and_deactivate_are_idempotent_and_identity_is_immutable(): void
    {
        $address = $this->createAddress();
        $id = $address->id();

        $address->deactivate();
        $address->deactivate();
        self::assertFalse($address->isActive());

        $address->activate();
        $address->activate();
        self::assertTrue($address->isActive());
        self::assertSame($id, $address->id());
    }

    private function createAddress(): Address
    {
        return new Address(
            new AddressId(new Uuid('550e8400-e29b-41d4-a716-446655440020')),
            AddressType::Visiting,
            new AddressLine('Finance Street 1'),
            null,
            new PostalCode('1234 AB'),
            new City('Amsterdam'),
            new CountryCode('nl'),
            true,
        );
    }
}
