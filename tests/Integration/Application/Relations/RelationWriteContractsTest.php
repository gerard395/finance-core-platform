<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Relations;

use App\Application\Relations\CreateRelation;
use App\Application\Relations\RelationCreator;
use App\Application\Relations\RelationReadRepository;
use App\Application\Relations\RelationUpdater;
use App\Application\Relations\RelationWriteResult;
use App\Application\Relations\UpdateRelation;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Relations\Entities\Contact;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

final class RelationWriteContractsTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $administrations = new EloquentAdministrationRepository;
        $administrations->save($this->administration(self::ADMINISTRATION_A, 'WRITE_A'));
        $administrations->save($this->administration(self::ADMINISTRATION_B, 'WRITE_B'));
    }

    public function test_create_uses_insert_semantics_and_preserves_tenant_ownership(): void
    {
        $result = $this->create()->execute(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->relationId(1),
            new RelationCode('rel-01'),
            new DisplayName('Created relation'),
            true,
            new VatIdentificationNumber('nl123456789b01'),
            new CountryCode('be'),
        );

        self::assertSame(RelationWriteResult::Success, $result);
        $this->assertDatabaseHas('relations', [
            'id' => $this->relationId(1)->toString(),
            'administration_id' => self::ADMINISTRATION_A,
            'code' => 'REL-01',
            'display_name' => 'Created relation',
            'active' => true,
            'vat_identification_number' => 'NL123456789B01',
            'fiscal_jurisdiction' => 'BE',
        ]);
        self::assertNull($this->reader()->findByIdForAdministration($this->administrationId(self::ADMINISTRATION_B), $this->relationId(1)));
    }

    public function test_fiscal_party_roundtrip_is_tenant_scoped_nullable_and_independent_from_addresses(): void
    {
        self::assertSame(RelationWriteResult::Success, $this->createRelation(1, 'FISCAL-01', 'Fiscal party'));
        $result = $this->update()->execute(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->relationId(1),
            new DisplayName('Fiscal party'),
            true,
            new VatIdentificationNumber('de123456789'),
            new CountryCode('DE'),
        );
        self::assertSame(RelationWriteResult::Success, $result);

        $party = (new EloquentRelationRepository)->findFiscalParty($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1));
        self::assertSame('DE123456789', $party?->vatIdentificationNumber?->toString());
        self::assertSame('DE', $party?->fiscalJurisdiction?->value());
        self::assertNull((new EloquentRelationRepository)->findFiscalParty($this->administrationId(self::ADMINISTRATION_B), $this->relationId(1)));

        DB::table('relation_addresses')->insert([
            'address_id' => $this->uuid('8', 9)->toString(), 'administration_id' => self::ADMINISTRATION_A,
            'relation_id' => $this->relationId(1)->toString(), 'address_type' => 'invoice',
            'address_line_1' => 'Rue 1', 'address_line_2' => null, 'postal_code' => '1000',
            'city' => 'Brussels', 'country_code' => 'BE', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        self::assertSame('DE', (new EloquentRelationRepository)->findFiscalParty($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1))?->fiscalJurisdiction?->value());

        self::assertSame(RelationWriteResult::Success, $this->create()->execute(
            $this->administrationId(self::ADMINISTRATION_A), $this->relationId(2), new RelationCode('FISCAL-02'),
            new DisplayName('Same VAT allowed'), true, new VatIdentificationNumber('DE123456789'), new CountryCode('DE'),
        ));
        $this->assertDatabaseCount('relations', 2);

        $this->update()->execute($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1), new DisplayName('Fiscal party'), true, null, null, true);
        $this->assertDatabaseHas('relation_addresses', ['address_id' => $this->uuid('8', 9)->toString(), 'country_code' => 'BE']);
        self::assertNull((new EloquentRelationRepository)->findFiscalParty($this->administrationId(self::ADMINISTRATION_A), $this->relationId(1))?->vatIdentificationNumber);
    }

    public function test_duplicate_identity_is_typed_and_never_overwrites_existing_relation(): void
    {
        self::assertSame(RelationWriteResult::Success, $this->createRelation(1, 'REL-01', 'Original'));

        $result = $this->create()->execute(
            $this->administrationId(self::ADMINISTRATION_B),
            $this->relationId(1),
            new RelationCode('OTHER'),
            new DisplayName('Attempted overwrite'),
            false,
        );

        self::assertSame(RelationWriteResult::DuplicateIdentity, $result);
        $this->assertDatabaseHas('relations', [
            'id' => $this->relationId(1)->toString(),
            'administration_id' => self::ADMINISTRATION_A,
            'code' => 'REL-01',
            'display_name' => 'Original',
            'active' => true,
        ]);
        $this->assertDatabaseMissing('relations', ['display_name' => 'Attempted overwrite']);
    }

    public function test_duplicate_code_is_typed_without_sql_details_or_partial_insert(): void
    {
        self::assertSame(RelationWriteResult::Success, $this->createRelation(1, 'REL-01', 'Original'));

        $result = $this->createRelation(2, 'rel-01', 'Duplicate code');

        self::assertSame(RelationWriteResult::DuplicateCode, $result);
        $this->assertDatabaseCount('relations', 1);
        $this->assertDatabaseMissing('relations', ['id' => $this->relationId(2)->toString()]);
    }

    public function test_update_uses_domain_mutations_and_preserves_identity_code_and_tenant(): void
    {
        self::assertSame(RelationWriteResult::Success, $this->createRelation(1, 'REL-01', 'Original'));

        $result = $this->update()->execute(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->relationId(1),
            new DisplayName('Renamed safely'),
            false,
        );

        self::assertSame(RelationWriteResult::Success, $result);
        $this->assertDatabaseHas('relations', [
            'id' => $this->relationId(1)->toString(),
            'administration_id' => self::ADMINISTRATION_A,
            'code' => 'REL-01',
            'display_name' => 'Renamed safely',
            'active' => false,
        ]);
    }

    public function test_unknown_and_cross_tenant_updates_have_identical_not_found_semantics_and_never_insert(): void
    {
        self::assertSame(RelationWriteResult::Success, $this->createRelation(1, 'REL-01', 'Tenant A'));
        $unknown = $this->update()->execute(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->relationId(99),
            new DisplayName('Unknown'),
            true,
        );
        $crossTenant = $this->update()->execute(
            $this->administrationId(self::ADMINISTRATION_B),
            $this->relationId(1),
            new DisplayName('Cross tenant'),
            false,
        );

        self::assertSame(RelationWriteResult::NotFound, $unknown);
        self::assertSame($unknown, $crossTenant);
        $this->assertDatabaseCount('relations', 1);
        $this->assertDatabaseHas('relations', ['id' => $this->relationId(1)->toString(), 'display_name' => 'Tenant A', 'active' => true]);
        $this->assertDatabaseMissing('relations', ['id' => $this->relationId(99)->toString()]);
    }

    public function test_create_keeps_loaded_child_guard(): void
    {
        $relation = $this->relation(1, 'REL-01', 'With child');
        $relation->addContact(new Contact(
            new ContactId($this->uuid('7', 1)),
            new ContactName('Contact Person'),
            null,
            null,
            ContactStatus::Active,
        ));
        $repository = new EloquentRelationRepository;

        try {
            $repository->create($this->administrationId(self::ADMINISTRATION_A), $relation);
            self::fail('Loaded Relation children must never be silently discarded during creation.');
        } catch (DomainException $exception) {
            self::assertSame('Relation child persistence is outside this repository contract.', $exception->getMessage());
        }

        $this->assertDatabaseCount('relations', 0);
    }

    public function test_relation_update_accepts_hydrated_children_and_preserves_every_child_type(): void
    {
        self::assertSame(RelationWriteResult::Success, $this->createRelation(1, 'REL-01', 'With children'));

        DB::table('relation_contacts')->insert([
            'contact_id' => $this->uuid('7', 1)->toString(),
            'administration_id' => self::ADMINISTRATION_A,
            'relation_id' => $this->relationId(1)->toString(),
            'contact_name' => 'Contact Person',
            'email' => 'contact@example.test',
            'phone' => '+31612345678',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('relation_addresses')->insert([
            'address_id' => $this->uuid('8', 1)->toString(),
            'administration_id' => self::ADMINISTRATION_A,
            'relation_id' => $this->relationId(1)->toString(),
            'address_type' => 'invoice',
            'address_line_1' => 'Reviewstraat 7',
            'address_line_2' => null,
            'postal_code' => '1234 AB',
            'city' => 'Utrecht',
            'country_code' => 'NL',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('relation_bank_accounts')->insert([
            'bank_account_id' => $this->uuid('9', 1)->toString(),
            'administration_id' => self::ADMINISTRATION_A,
            'relation_id' => $this->relationId(1)->toString(),
            'iban' => 'NL91ABNA0417164300',
            'bic' => 'ABNANL2A',
            'account_name' => 'Review Account',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->update()->execute(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->relationId(1),
            new DisplayName('Renamed with children'),
            false,
        );

        self::assertSame(RelationWriteResult::Success, $result);
        $this->assertDatabaseHas('relations', ['id' => $this->relationId(1)->toString(), 'display_name' => 'Renamed with children', 'active' => false]);
        $this->assertDatabaseHas('relation_contacts', ['contact_id' => $this->uuid('7', 1)->toString(), 'contact_name' => 'Contact Person', 'status' => 'active']);
        $this->assertDatabaseHas('relation_addresses', ['address_id' => $this->uuid('8', 1)->toString(), 'address_line_1' => 'Reviewstraat 7', 'active' => true]);
        $this->assertDatabaseHas('relation_bank_accounts', ['bank_account_id' => $this->uuid('9', 1)->toString(), 'account_name' => 'Review Account', 'active' => true]);
        $this->assertDatabaseCount('relation_contacts', 1);
        $this->assertDatabaseCount('relation_addresses', 1);
        $this->assertDatabaseCount('relation_bank_accounts', 1);
    }

    public function test_relation_code_has_no_mutation_api_and_write_contracts_are_bound(): void
    {
        $methods = array_map(static fn ($method): string => $method->getName(), (new ReflectionClass(Relation::class))->getMethods());

        self::assertNotContains('changeCode', $methods);
        self::assertNotContains('setCode', $methods);
        self::assertInstanceOf(EloquentRelationRepository::class, $this->app->make(RelationCreator::class));
        self::assertInstanceOf(EloquentRelationRepository::class, $this->app->make(RelationUpdater::class));
    }

    private function createRelation(int $sequence, string $code, string $name): RelationWriteResult
    {
        return $this->create()->execute(
            $this->administrationId(self::ADMINISTRATION_A),
            $this->relationId($sequence),
            new RelationCode($code),
            new DisplayName($name),
        );
    }

    private function create(): CreateRelation
    {
        return $this->app->make(CreateRelation::class);
    }

    private function update(): UpdateRelation
    {
        return $this->app->make(UpdateRelation::class);
    }

    private function reader(): RelationReadRepository
    {
        return $this->app->make(RelationReadRepository::class);
    }

    private function relation(int $sequence, string $code, string $name): Relation
    {
        return new Relation($this->relationId($sequence), new RelationCode($code), new DisplayName($name), true);
    }

    private function relationId(int $sequence): RelationId
    {
        return new RelationId($this->uuid('6', $sequence));
    }

    private function uuid(string $prefix, int $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $sequence));
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function administration(string $id, string $code): Administration
    {
        return new Administration(
            $this->administrationId($id),
            new AdministrationCode($code),
            new AdministrationName($code),
            null,
            new Currency('EUR'),
            AdministrationStatus::Active,
        );
    }
}
