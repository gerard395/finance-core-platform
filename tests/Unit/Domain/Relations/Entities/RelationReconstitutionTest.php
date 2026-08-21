<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Address;
use App\Domain\Relations\Entities\BankAccount;
use App\Domain\Relations\Entities\Contact;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\Enums\BankAccountStatus;
use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RelationReconstitutionTest extends TestCase
{
    public function test_it_reconstitutes_an_active_relation_without_children(): void
    {
        $id = $this->relationId();
        $code = new RelationCode('REL-001');

        $relation = Relation::reconstitute($id, $code, new DisplayName('Existing Relation'), true, [], [], []);

        self::assertSame($id, $relation->id());
        self::assertSame($code, $relation->code());
        self::assertSame('Existing Relation', $relation->displayName()->value());
        self::assertTrue($relation->isActive());
        self::assertSame([], $relation->contacts());
        self::assertSame([], $relation->addresses());
        self::assertSame([], $relation->bankAccounts());
    }

    public function test_it_reconstitutes_an_inactive_relation_with_complete_mixed_child_state(): void
    {
        $firstContact = $this->contact(10, ContactStatus::Inactive);
        $secondContact = $this->contact(11, ContactStatus::Active);
        $address = $this->address(20, false);
        $bankAccount = $this->bankAccount(30, BankAccountStatus::Inactive);

        $relation = Relation::reconstitute(
            $this->relationId(),
            new RelationCode('REL-001'),
            new DisplayName('Existing Relation'),
            false,
            [$firstContact, $secondContact],
            [$address],
            [$bankAccount],
        );

        self::assertFalse($relation->isActive());
        self::assertSame([$firstContact, $secondContact], $relation->contacts());
        self::assertSame([$address], $relation->addresses());
        self::assertSame([$bankAccount], $relation->bankAccounts());
        self::assertSame($firstContact, $relation->contact($firstContact->id()));
        self::assertSame($address, $relation->address($address->id()));
        self::assertSame($bankAccount, $relation->bankAccount($bankAccount->id()));
        self::assertFalse($firstContact->isActive());
        self::assertFalse($address->isActive());
        self::assertFalse($bankAccount->isActive());
    }

    public function test_it_rejects_duplicate_contact_identities(): void
    {
        $contact = $this->contact(10);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Relation already contains a Contact with this identity.');

        Relation::reconstitute(
            $this->relationId(),
            new RelationCode('REL-001'),
            new DisplayName('Existing Relation'),
            true,
            [$contact, $contact],
            [],
            [],
        );
    }

    public function test_it_rejects_duplicate_address_identities(): void
    {
        $address = $this->address(20);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Relation already contains an Address with this identity.');

        Relation::reconstitute(
            $this->relationId(),
            new RelationCode('REL-001'),
            new DisplayName('Existing Relation'),
            true,
            [],
            [$address, $address],
            [],
        );
    }

    public function test_it_rejects_duplicate_bank_account_identities(): void
    {
        $bankAccount = $this->bankAccount(30);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Relation already contains a BankAccount with this identity.');

        Relation::reconstitute(
            $this->relationId(),
            new RelationCode('REL-001'),
            new DisplayName('Existing Relation'),
            true,
            [],
            [],
            [$bankAccount, $bankAccount],
        );
    }

    public function test_reconstituted_inactive_children_reactivate_with_the_same_identity(): void
    {
        $contact = $this->contact(10, ContactStatus::Inactive);
        $address = $this->address(20, false);
        $bankAccount = $this->bankAccount(30, BankAccountStatus::Inactive);
        $relation = Relation::reconstitute(
            $this->relationId(),
            new RelationCode('REL-001'),
            new DisplayName('Existing Relation'),
            true,
            [$contact],
            [$address],
            [$bankAccount],
        );
        $contactId = $contact->id();
        $addressId = $address->id();
        $bankAccountId = $bankAccount->id();

        $relation->contact($contactId)?->activate();
        $relation->address($addressId)?->activate();
        $relation->bankAccount($bankAccountId)?->activate();

        self::assertTrue($contact->isActive());
        self::assertTrue($address->isActive());
        self::assertTrue($bankAccount->isActive());
        self::assertSame($contactId, $contact->id());
        self::assertSame($addressId, $address->id());
        self::assertSame($bankAccountId, $bankAccount->id());
    }

    private function relationId(): RelationId
    {
        return new RelationId($this->uuid(1));
    }

    private function contact(int $sequence, ContactStatus $status = ContactStatus::Active): Contact
    {
        return new Contact(
            new ContactId($this->uuid($sequence)),
            new ContactName('Contact '.$sequence),
            null,
            null,
            $status,
        );
    }

    private function address(int $sequence, bool $active = true): Address
    {
        return new Address(
            new AddressId($this->uuid($sequence)),
            AddressType::Visiting,
            new AddressLine('Street '.$sequence),
            null,
            new PostalCode('1234 AB'),
            new City('Amsterdam'),
            new CountryCode('NL'),
            $active,
        );
    }

    private function bankAccount(int $sequence, BankAccountStatus $status = BankAccountStatus::Active): BankAccount
    {
        return new BankAccount(
            new BankAccountId($this->uuid($sequence)),
            new Iban('NL91ABNA0417164300'),
            null,
            new AccountName('Account '.$sequence),
            $status,
        );
    }

    private function uuid(int $sequence): Uuid
    {
        return new Uuid(sprintf('550e8400-e29b-41d4-a716-%012d', $sequence));
    }
}
