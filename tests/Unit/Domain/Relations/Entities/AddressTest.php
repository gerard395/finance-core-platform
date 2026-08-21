<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Address;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
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

    public function test_it_changes_all_mutable_content_atomically_without_changing_identity_type_or_status(): void
    {
        $address = $this->createAddress();
        $id = $address->id();

        $address->changeDetails(
            new AddressLine('Changed Street 2'),
            new AddressLine('Unit 3'),
            new PostalCode('5678 CD'),
            new City('Rotterdam'),
            new CountryCode('be'),
        );

        self::assertSame($id, $address->id());
        self::assertSame(AddressType::Visiting, $address->type());
        self::assertSame('Changed Street 2', $address->addressLine()->value());
        self::assertSame('Unit 3', $address->addressLine2()?->value());
        self::assertSame('5678 CD', $address->postalCode()->value());
        self::assertSame('Rotterdam', $address->city()->value());
        self::assertSame('BE', $address->countryCode()->value());
        self::assertTrue($address->isActive());
    }

    public function test_content_change_is_idempotent_and_preserves_inactive_lifecycle(): void
    {
        $address = $this->createAddress();
        $address->deactivate();
        $addressLine = new AddressLine('Changed Street 2');
        $postalCode = new PostalCode('5678 CD');
        $city = new City('Rotterdam');
        $countryCode = new CountryCode('BE');

        $address->changeDetails($addressLine, null, $postalCode, $city, $countryCode);
        $address->changeDetails($addressLine, null, $postalCode, $city, $countryCode);

        self::assertFalse($address->isActive());
        self::assertSame($addressLine, $address->addressLine());
        self::assertNull($address->addressLine2());
        self::assertSame($postalCode, $address->postalCode());
        self::assertSame($city, $address->city());
        self::assertSame($countryCode, $address->countryCode());
    }

    public function test_lifecycle_changes_preserve_changed_content_and_identity(): void
    {
        $address = $this->createAddress();
        $id = $address->id();
        $address->changeDetails(
            new AddressLine('Changed Street 2'),
            null,
            new PostalCode('5678 CD'),
            new City('Rotterdam'),
            new CountryCode('BE'),
        );

        $address->deactivate();
        self::assertSame('Changed Street 2', $address->addressLine()->value());
        self::assertFalse($address->isActive());

        $address->activate();
        self::assertSame('Changed Street 2', $address->addressLine()->value());
        self::assertTrue($address->isActive());
        self::assertSame($id, $address->id());
    }

    public function test_relation_managed_address_mutates_without_replacement_or_relation_identity_change(): void
    {
        $relationId = new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $relation = new Relation($relationId, new RelationCode('REL-001'), new DisplayName('Relation'), true);
        $address = $this->createAddress();
        $relation->addAddress($address);

        $relation->address($address->id())?->changeDetails(
            new AddressLine('Changed Street 2'),
            null,
            new PostalCode('5678 CD'),
            new City('Rotterdam'),
            new CountryCode('BE'),
        );

        self::assertSame($relationId, $relation->id());
        self::assertSame([$address], $relation->addresses());
        self::assertSame('Changed Street 2', $relation->address($address->id())?->addressLine()->value());
    }

    public function test_changed_address_is_reconstituted_as_exact_factual_state(): void
    {
        $address = $this->createAddress();
        $address->changeDetails(
            new AddressLine('Changed Street 2'),
            new AddressLine('Unit 3'),
            new PostalCode('5678 CD'),
            new City('Rotterdam'),
            new CountryCode('BE'),
        );
        $address->deactivate();

        $relation = Relation::reconstitute(
            new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new RelationCode('REL-001'),
            new DisplayName('Relation'),
            true,
            [],
            [$address],
            [],
        );

        self::assertSame($address, $relation->address($address->id()));
        self::assertSame('Changed Street 2', $relation->address($address->id())?->addressLine()->value());
        self::assertSame(AddressType::Visiting, $relation->address($address->id())?->type());
        self::assertFalse($relation->address($address->id())?->isActive());
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
