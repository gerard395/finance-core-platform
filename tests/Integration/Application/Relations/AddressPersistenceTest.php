<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Relations;

use App\Application\Relations\ActivateAddress;
use App\Application\Relations\AddressReadRepository;
use App\Application\Relations\AddressWriteResult;
use App\Application\Relations\ContactWriteResult;
use App\Application\Relations\CreateAddress;
use App\Application\Relations\CreateContact;
use App\Application\Relations\DeactivateAddress;
use App\Application\Relations\UpdateAddress;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Relations\Entities\BankAccount;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\Enums\BankAccountStatus;
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
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAddressWriter;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AddressPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMIN_B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMIN_A, 'ADDRESS_A'));
        $administrations->save($this->administration(self::ADMIN_B, 'ADDRESS_B'));
        $relations = new EloquentRelationRepository;
        $relations->save($this->admin(self::ADMIN_A), $this->relation(1));
        $relations->save($this->admin(self::ADMIN_A), $this->relation(2));
        $relations->save($this->admin(self::ADMIN_B), $this->relation(3));
    }

    public function test_roundtrip_ordering_multiple_same_type_and_identical_content_preserve_contacts(): void
    {
        self::assertSame(ContactWriteResult::Success, $this->app->make(CreateContact::class)->execute($this->admin(self::ADMIN_A), $this->relationId(1), new ContactId($this->uuid('7', 1)), new ContactName('Preserved Contact'), null, null));
        $this->createAddress(2, AddressType::Visiting, 'Same line', null, '1000 AA', 'Brussels', 'be');
        $this->createAddress(1, AddressType::Visiting, 'Same line', null, '1000 AA', 'Brussels', 'be');
        $this->createAddress(3, AddressType::Invoice, 'Invoice line', 'Floor 2', '75001', 'Paris', 'fr');

        $items = $this->app->make(AddressReadRepository::class)->listForRelation($this->admin(self::ADMIN_A), $this->relationId(1));
        self::assertSame([$this->addressId(3)->toString(), $this->addressId(1)->toString(), $this->addressId(2)->toString()], array_map(fn ($item): string => $item->id->toString(), $items));
        $detail = $this->app->make(AddressReadRepository::class)->findForRelation($this->admin(self::ADMIN_A), $this->relationId(1), $this->addressId(3));
        self::assertSame(AddressType::Invoice, $detail?->type);
        self::assertSame('Floor 2', $detail?->addressLine2?->value());
        self::assertSame('FR', $detail?->countryCode->value());

        $relation = (new EloquentRelationRepository)->findByIdForAdministration($this->admin(self::ADMIN_A), $this->relationId(1));
        self::assertCount(1, $relation?->contacts());
        self::assertCount(3, $relation?->addresses());
        self::assertSame('Preserved Contact', $relation?->contacts()[0]->name()->value());
    }

    public function test_update_all_mutable_content_and_lifecycle_preserve_identity_type_and_record(): void
    {
        $this->createAddress(1, AddressType::Delivery, 'Old line', null, '1000', 'Old City', 'NL');
        $update = $this->app->make(UpdateAddress::class);
        self::assertSame(AddressWriteResult::Success, $update->execute($this->admin(self::ADMIN_A), $this->relationId(1), $this->addressId(1), new AddressLine('New line'), new AddressLine('Unit 4'), new PostalCode('2000 XY'), new City('New City'), new CountryCode('DE')));
        self::assertSame(AddressWriteResult::Success, $update->execute($this->admin(self::ADMIN_A), $this->relationId(1), $this->addressId(1), new AddressLine('Final line'), null, new PostalCode('3000'), new City('Final City'), new CountryCode('BE')));
        self::assertSame(AddressWriteResult::Success, $this->app->make(DeactivateAddress::class)->execute($this->admin(self::ADMIN_A), $this->relationId(1), $this->addressId(1)));
        self::assertSame(AddressWriteResult::Success, $this->app->make(DeactivateAddress::class)->execute($this->admin(self::ADMIN_A), $this->relationId(1), $this->addressId(1)));
        $this->assertDatabaseHas('relation_addresses', ['address_id' => $this->addressId(1)->toString(), 'address_type' => 'delivery', 'address_line_1' => 'Final line', 'address_line_2' => null, 'postal_code' => '3000', 'city' => 'Final City', 'country_code' => 'BE', 'active' => false]);
        self::assertSame(AddressWriteResult::Success, $this->app->make(ActivateAddress::class)->execute($this->admin(self::ADMIN_A), $this->relationId(1), $this->addressId(1)));
        $this->assertDatabaseHas('relation_addresses', ['address_id' => $this->addressId(1)->toString(), 'address_type' => 'delivery', 'active' => true]);
        $this->assertDatabaseCount('relation_addresses', 1);
    }

    public function test_safe_not_found_duplicate_and_tenant_relation_isolation(): void
    {
        $this->createAddress(1, AddressType::Postal, 'Postal line', null, '12345', 'Berlin', 'DE');
        self::assertSame(AddressWriteResult::DuplicateIdentity, $this->app->make(CreateAddress::class)->execute($this->admin(self::ADMIN_A), $this->relationId(2), $this->addressId(1), AddressType::Invoice, new AddressLine('Other line'), null, new PostalCode('23456'), new City('Hamburg'), new CountryCode('DE')));
        self::assertSame(AddressWriteResult::NotFound, $this->app->make(UpdateAddress::class)->execute($this->admin(self::ADMIN_A), $this->relationId(2), $this->addressId(1), new AddressLine('Hidden line'), null, new PostalCode('23456'), new City('Hamburg'), new CountryCode('DE')));
        self::assertSame(AddressWriteResult::NotFound, $this->app->make(DeactivateAddress::class)->execute($this->admin(self::ADMIN_B), $this->relationId(1), $this->addressId(1)));
        self::assertNull($this->app->make(AddressReadRepository::class)->findForRelation($this->admin(self::ADMIN_A), $this->relationId(2), $this->addressId(1)));
        self::assertNull($this->app->make(AddressReadRepository::class)->findForRelation($this->admin(self::ADMIN_B), $this->relationId(1), $this->addressId(1)));
    }

    public function test_database_rejects_cross_tenant_parent_and_restricts_delete(): void
    {
        try {
            DB::table('relation_addresses')->insert(['address_id' => $this->addressId(9)->toString(), 'administration_id' => self::ADMIN_B, 'relation_id' => $this->relationId(1)->toString(), 'address_type' => 'visiting', 'address_line_1' => 'Cross tenant', 'address_line_2' => null, 'postal_code' => '1000', 'city' => 'City', 'country_code' => 'NL', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            self::fail('Expected composite tenant foreign key rejection.');
        } catch (QueryException) {
            self::assertTrue(true);
        }
        $this->createAddress(1, AddressType::Visiting, 'Owned line', null, '1000', 'City', 'NL');
        $this->expectException(QueryException::class);
        DB::table('relations')->where('id', $this->relationId(1)->toString())->delete();
    }

    public function test_address_writer_rejects_loaded_bank_account_state(): void
    {
        $relation = $this->relation(1);
        $relation->addBankAccount(new BankAccount(new BankAccountId($this->uuid('9', 1)), new Iban('NL91ABNA0417164300'), null, new AccountName('Account name'), BankAccountStatus::Active));
        $this->expectException(DomainException::class);
        (new EloquentAddressWriter)->update($this->admin(self::ADMIN_A), $relation, $this->addressId(1));
    }

    private function createAddress(int $sequence, AddressType $type, string $line1, ?string $line2, string $postalCode, string $city, string $country): void
    {
        self::assertSame(AddressWriteResult::Success, $this->app->make(CreateAddress::class)->execute($this->admin(self::ADMIN_A), $this->relationId(1), $this->addressId($sequence), $type, new AddressLine($line1), $line2 === null ? null : new AddressLine($line2), new PostalCode($postalCode), new City($city), new CountryCode($country)));
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration($this->admin($id), new AdministrationCode($code), new AdministrationName($code), null, new Currency('EUR'), AdministrationStatus::Active);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function relation(int $sequence): Relation
    {
        return new Relation($this->relationId($sequence), new RelationCode('REL-'.$sequence), new DisplayName('Relation '.$sequence), true);
    }

    private function relationId(int $sequence): RelationId
    {
        return new RelationId($this->uuid('6', $sequence));
    }

    private function addressId(int $sequence): AddressId
    {
        return new AddressId($this->uuid('8', $sequence));
    }

    private function uuid(string $prefix, int $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $sequence));
    }
}
