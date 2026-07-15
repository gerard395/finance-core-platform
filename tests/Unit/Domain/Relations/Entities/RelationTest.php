<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Address;
use App\Domain\Relations\Entities\Contact;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RelationTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $relation = $this->createRelation();

        self::assertSame('RELATION_001', $relation->code()->value());
        self::assertSame('Finance Supplier', $relation->displayName()->value());
        self::assertTrue($relation->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity_or_code(): void
    {
        $relation = $this->createRelation();
        $id = $relation->id();
        $code = $relation->code();

        $relation->rename(new DisplayName('Renamed Relation'));

        self::assertSame('Renamed Relation', $relation->displayName()->value());
        self::assertSame($id, $relation->id());
        self::assertSame($code, $relation->code());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $relation = $this->createRelation();

        $relation->deactivate();
        $relation->deactivate();
        self::assertFalse($relation->isActive());

        $relation->activate();
        $relation->activate();
        self::assertTrue($relation->isActive());
    }

    public function test_it_starts_without_contacts(): void
    {
        self::assertSame([], $this->createRelation()->contacts());
    }

    public function test_it_can_add_and_read_a_contact(): void
    {
        $relation = $this->createRelation();
        $contact = $this->createContact();

        $relation->addContact($contact);

        self::assertTrue($relation->hasContact($contact->id()));
        self::assertSame($contact, $relation->contact($contact->id()));
        self::assertSame([$contact], $relation->contacts());
    }

    public function test_it_can_add_multiple_contacts_with_different_identities(): void
    {
        $relation = $this->createRelation();
        $first = $this->createContact('550e8400-e29b-41d4-a716-446655440010', 'First Contact');
        $second = $this->createContact('550e8400-e29b-41d4-a716-446655440011', 'Second Contact');

        $relation->addContact($first);
        $relation->addContact($second);

        self::assertSame([$first, $second], $relation->contacts());
    }

    public function test_has_contact_and_contact_report_unknown_identity(): void
    {
        $relation = $this->createRelation();
        $unknownId = new ContactId(new Uuid('550e8400-e29b-41d4-a716-446655440099'));

        self::assertFalse($relation->hasContact($unknownId));
        self::assertNull($relation->contact($unknownId));
    }

    public function test_duplicate_contact_identity_is_rejected_without_replacement(): void
    {
        $relation = $this->createRelation();
        $contact = $this->createContact();
        $duplicate = $this->createContact($contact->id()->toString(), 'Different Contact');
        $relation->addContact($contact);

        try {
            $relation->addContact($duplicate);
            self::fail('Expected duplicate ContactId to be rejected.');
        } catch (DomainException) {
            self::assertSame($contact, $relation->contact($contact->id()));
        }
    }

    public function test_it_can_remove_a_contact_and_unknown_removal_is_idempotent(): void
    {
        $relation = $this->createRelation();
        $contact = $this->createContact();
        $relation->addContact($contact);

        $relation->removeContact($contact->id());
        $relation->removeContact($contact->id());

        self::assertFalse($relation->hasContact($contact->id()));
        self::assertSame([], $relation->contacts());
    }

    public function test_contact_changes_use_the_object_managed_by_relation(): void
    {
        $relation = $this->createRelation();
        $contact = $this->createContact();
        $relation->addContact($contact);

        $relation->contact($contact->id())?->rename(new ContactName('Managed Contact'));

        self::assertSame('Managed Contact', $relation->contact($contact->id())?->name()->value());
    }

    public function test_contact_management_does_not_change_relation_identity(): void
    {
        $relation = $this->createRelation();
        $id = $relation->id();
        $contact = $this->createContact();

        $relation->addContact($contact);
        $relation->removeContact($contact->id());

        self::assertSame($id, $relation->id());
    }

    public function test_it_starts_without_addresses_and_can_add_and_remove_one(): void
    {
        $relation = $this->createRelation();
        $address = $this->createAddress();

        self::assertSame([], $relation->addresses());
        $relation->addAddress($address);
        self::assertTrue($relation->hasAddress($address->id()));
        self::assertSame($address, $relation->address($address->id()));

        $relation->removeAddress($address->id());
        $relation->removeAddress($address->id());
        self::assertFalse($relation->hasAddress($address->id()));
    }

    public function test_duplicate_address_identity_is_rejected_without_replacement(): void
    {
        $relation = $this->createRelation();
        $address = $this->createAddress();
        $duplicate = $this->createAddress($address->id()->toString());
        $relation->addAddress($address);

        try {
            $relation->addAddress($duplicate);
            self::fail('Expected duplicate AddressId to be rejected.');
        } catch (DomainException) {
            self::assertSame($address, $relation->address($address->id()));
        }
    }

    public function test_address_ownership_does_not_change_relation_identity(): void
    {
        $relation = $this->createRelation();
        $id = $relation->id();
        $relation->addAddress($this->createAddress());

        self::assertSame($id, $relation->id());
    }

    private function createRelation(): Relation
    {
        return new Relation(
            new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new RelationCode('relation_001'),
            new DisplayName('Finance Supplier'),
            true,
        );
    }

    private function createContact(
        string $uuid = '550e8400-e29b-41d4-a716-446655440010',
        string $name = 'Finance Contact',
    ): Contact {
        return new Contact(
            new ContactId(new Uuid($uuid)),
            new ContactName($name),
            null,
            null,
            ContactStatus::Active,
        );
    }

    private function createAddress(string $uuid = '550e8400-e29b-41d4-a716-446655440020'): Address
    {
        return new Address(
            new AddressId(new Uuid($uuid)),
            AddressType::Visiting,
            new AddressLine('Finance Street 1'),
            null,
            new PostalCode('1234 AB'),
            new City('Amsterdam'),
            new CountryCode('NL'),
            true,
        );
    }
}
