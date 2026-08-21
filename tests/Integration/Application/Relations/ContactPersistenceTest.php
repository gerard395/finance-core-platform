<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Relations;

use App\Application\Relations\ActivateContact;
use App\Application\Relations\ContactReadRepository;
use App\Application\Relations\ContactWriteResult;
use App\Application\Relations\CreateContact;
use App\Application\Relations\DeactivateContact;
use App\Application\Relations\UpdateContact;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Relations\Entities\Address;
use App\Domain\Relations\Entities\BankAccount;
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
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PhoneNumber;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentContactWriter;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ContactPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMIN_B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMIN_A, 'CONTACT_A'));
        $administrations->save($this->administration(self::ADMIN_B, 'CONTACT_B'));
        $relations = new EloquentRelationRepository;
        $relations->save($this->administrationId(self::ADMIN_A), $this->relation(1, 'A-1'));
        $relations->save($this->administrationId(self::ADMIN_A), $this->relation(2, 'A-2'));
        $relations->save($this->administrationId(self::ADMIN_B), $this->relation(3, 'B-3'));
    }

    public function test_create_hydration_nullable_state_ordering_and_scoped_reads(): void
    {
        $create = $this->app->make(CreateContact::class);
        self::assertSame(ContactWriteResult::Success, $create->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(2), new ContactName('Zed Person'), null, null));
        self::assertSame(ContactWriteResult::Success, $create->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(1), new ContactName('Amy Person'), new EmailAddress('AMY@example.com'), new PhoneNumber('+31 20 123 4567')));

        $reader = $this->app->make(ContactReadRepository::class);
        $items = $reader->listForRelation($this->administrationId(self::ADMIN_A), $this->relationId(1));
        self::assertSame([$this->contactId(1)->toString(), $this->contactId(2)->toString()], array_map(fn ($item): string => $item->id->toString(), $items));
        self::assertSame(ContactStatus::Active, $items[0]->status);
        $detail = $reader->findForRelation($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(1));
        self::assertSame('amy@example.com', $detail?->emailAddress?->toString());
        self::assertSame('+31 20 123 4567', $detail?->phoneNumber?->toString());
        self::assertNull($reader->findForRelation($this->administrationId(self::ADMIN_B), $this->relationId(1), $this->contactId(1)));
        self::assertNull($reader->findForRelation($this->administrationId(self::ADMIN_A), $this->relationId(2), $this->contactId(1)));

        $relation = (new EloquentRelationRepository)->findByIdForAdministration($this->administrationId(self::ADMIN_A), $this->relationId(1));
        self::assertNotNull($relation);
        self::assertCount(2, $relation->contacts());
        self::assertSame('Amy Person', $relation->contact($this->contactId(1))?->name()->toString());
        self::assertNull($relation->contact($this->contactId(2))?->emailAddress());
    }

    public function test_update_and_lifecycle_preserve_identity_ownership_and_never_delete(): void
    {
        $this->createContact(1, 'Original Person', 'old@example.com', '+31 20 111 1111');
        $update = $this->app->make(UpdateContact::class);
        self::assertSame(ContactWriteResult::Success, $update->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(1), new ContactName('Changed Person'), new EmailAddress('changed@example.com'), new PhoneNumber('+31 20 222 2222')));
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $this->contactId(1)->toString(), 'contact_name' => 'Changed Person', 'email' => 'changed@example.com', 'phone' => '+31 20 222 2222']);
        self::assertSame(ContactWriteResult::Success, $update->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(1), new ContactName('Renamed Person'), null, null));
        self::assertSame(ContactWriteResult::Success, $this->app->make(DeactivateContact::class)->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(1)));
        self::assertSame(ContactWriteResult::Success, $this->app->make(DeactivateContact::class)->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(1)));
        $this->assertDatabaseCount('relation_contacts', 1);
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $this->contactId(1)->toString(), 'relation_id' => $this->relationId(1)->toString(), 'contact_name' => 'Renamed Person', 'email' => null, 'phone' => null, 'status' => 'inactive']);

        $items = $this->app->make(ContactReadRepository::class)->listForRelation($this->administrationId(self::ADMIN_A), $this->relationId(1));
        self::assertSame(ContactStatus::Inactive, $items[0]->status);
        self::assertSame(ContactWriteResult::Success, $this->app->make(ActivateContact::class)->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(1)));
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $this->contactId(1)->toString(), 'status' => 'active']);
        $this->assertDatabaseCount('relation_contacts', 1);
    }

    public function test_not_found_duplicate_and_duplicate_content_semantics(): void
    {
        $this->createContact(1, 'Same Person', 'same@example.com', null);
        self::assertSame(ContactWriteResult::DuplicateIdentity, $this->app->make(CreateContact::class)->execute($this->administrationId(self::ADMIN_A), $this->relationId(2), $this->contactId(1), new ContactName('Other Person'), null, null));
        self::assertSame(ContactWriteResult::Success, $this->app->make(CreateContact::class)->execute($this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId(2), new ContactName('Same Person'), new EmailAddress('same@example.com'), null));
        self::assertSame(ContactWriteResult::NotFound, $this->app->make(UpdateContact::class)->execute($this->administrationId(self::ADMIN_A), $this->relationId(2), $this->contactId(1), new ContactName('Hidden Person'), null, null));
        self::assertSame(ContactWriteResult::NotFound, $this->app->make(DeactivateContact::class)->execute($this->administrationId(self::ADMIN_B), $this->relationId(1), $this->contactId(1)));
        $this->assertDatabaseCount('relation_contacts', 2);
    }

    public function test_database_rejects_cross_tenant_parent(): void
    {
        $this->expectException(QueryException::class);
        DB::table('relation_contacts')->insert([
            'contact_id' => $this->contactId(9)->toString(), 'administration_id' => self::ADMIN_B,
            'relation_id' => $this->relationId(1)->toString(), 'contact_name' => 'Cross Tenant',
            'email' => null, 'phone' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_database_restricts_relation_delete_and_lifecycle_does_not_delete_contact(): void
    {
        $this->createContact(1, 'Historical Person', null, null);

        $this->expectException(QueryException::class);
        DB::table('relations')->where('id', $this->relationId(1)->toString())->delete();
    }

    public function test_contact_writer_accepts_supported_loaded_address_state_without_removing_it(): void
    {
        $this->createContact(1, 'Address aware contact', null, null);
        $relation = (new EloquentRelationRepository)->findByIdForAdministration($this->administrationId(self::ADMIN_A), $this->relationId(1));
        self::assertNotNull($relation);
        $relation->addAddress(new Address($this->addressId(1), AddressType::Visiting, new AddressLine('Main Street 1'), null, new PostalCode('1234 AB'), new City('Amsterdam'), new CountryCode('NL'), true));

        self::assertSame(ContactWriteResult::Success, (new EloquentContactWriter)->update($this->administrationId(self::ADMIN_A), $relation, $this->contactId(1)));
        self::assertTrue($relation->hasAddress($this->addressId(1)));
    }

    public function test_contact_writer_rejects_future_loaded_bank_account_state(): void
    {
        $relation = $this->relation(1, 'A-1');
        $relation->addBankAccount(new BankAccount($this->bankAccountId(1), new Iban('NL91ABNA0417164300'), null, new AccountName('Relation account'), BankAccountStatus::Active));

        $this->expectException(DomainException::class);
        (new EloquentContactWriter)->update($this->administrationId(self::ADMIN_A), $relation, $this->contactId(1));
    }

    private function createContact(int $sequence, string $name, ?string $email, ?string $phone): void
    {
        self::assertSame(ContactWriteResult::Success, $this->app->make(CreateContact::class)->execute(
            $this->administrationId(self::ADMIN_A), $this->relationId(1), $this->contactId($sequence), new ContactName($name),
            $email === null ? null : new EmailAddress($email), $phone === null ? null : new PhoneNumber($phone),
        ));
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration($this->administrationId($id), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function relation(int $sequence, string $code): Relation
    {
        return new Relation($this->relationId($sequence), new RelationCode($code), new DisplayName('Relation '.$sequence), true);
    }

    private function relationId(int $sequence): RelationId
    {
        return new RelationId(new Uuid(sprintf('60000000-0000-4000-8000-%012d', $sequence)));
    }

    private function contactId(int $sequence): ContactId
    {
        return new ContactId(new Uuid(sprintf('70000000-0000-4000-8000-%012d', $sequence)));
    }

    private function addressId(int $sequence): AddressId
    {
        return new AddressId(new Uuid(sprintf('80000000-0000-4000-8000-%012d', $sequence)));
    }

    private function bankAccountId(int $sequence): BankAccountId
    {
        return new BankAccountId(new Uuid(sprintf('90000000-0000-4000-8000-%012d', $sequence)));
    }
}
