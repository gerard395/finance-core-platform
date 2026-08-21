<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Relations;

use App\Application\Relations\ActivateBankAccount;
use App\Application\Relations\AddressWriteResult;
use App\Application\Relations\BankAccountReadRepository;
use App\Application\Relations\BankAccountWriteResult;
use App\Application\Relations\ContactWriteResult;
use App\Application\Relations\CreateAddress;
use App\Application\Relations\CreateBankAccount;
use App\Application\Relations\CreateContact;
use App\Application\Relations\DeactivateBankAccount;
use App\Application\Relations\UpdateBankAccount;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
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
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BankAccountPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const string A = '10000000-0000-4000-8000-000000000001';

    private const string B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $repository = new EloquentAdministrationRepository;
        $repository->save($this->administration(self::A, 'BANK_A'));
        $repository->save($this->administration(self::B, 'BANK_B'));
        $relations = new EloquentRelationRepository;
        $relations->save($this->admin(self::A), $this->relation(1));
        $relations->save($this->admin(self::A), $this->relation(2));
        $relations->save($this->admin(self::B), $this->relation(3));
    }

    public function test_roundtrip_ordering_duplicates_and_all_child_preservation(): void
    {
        self::assertSame(ContactWriteResult::Success, $this->app->make(CreateContact::class)->execute($this->admin(self::A), $this->relationId(1), new ContactId($this->uuid('7', 1)), new ContactName('Contact Person'), null, null));
        self::assertSame(AddressWriteResult::Success, $this->app->make(CreateAddress::class)->execute($this->admin(self::A), $this->relationId(1), new AddressId($this->uuid('8', 1)), AddressType::Visiting, new AddressLine('Address line'), null, new PostalCode('1000'), new City('City'), new CountryCode('NL')));
        $this->create(2, 'NL91ABNA0417164300', null, 'Zulu Account');
        $this->create(1, 'NL91ABNA0417164300', 'ABNANL2A', 'Alpha Account');
        $items = $this->app->make(BankAccountReadRepository::class)->listForRelation($this->admin(self::A), $this->relationId(1));
        self::assertSame([$this->bankId(1)->toString(), $this->bankId(2)->toString()], array_map(fn ($item): string => $item->id->toString(), $items));
        self::assertNull($items[1]->bic);
        self::assertSame('ABNANL2A', $items[0]->bic?->value());
        $relation = (new EloquentRelationRepository)->findByIdForAdministration($this->admin(self::A), $this->relationId(1));
        self::assertCount(1, $relation?->contacts());
        self::assertCount(1, $relation?->addresses());
        self::assertCount(2, $relation?->bankAccounts());
    }

    public function test_update_and_lifecycle_preserve_immutable_content_without_delete(): void
    {
        $this->create(1, 'NL91ABNA0417164300', 'ABNANL2A', 'Original Account');
        self::assertSame(BankAccountWriteResult::Success, $this->app->make(UpdateBankAccount::class)->execute($this->admin(self::A), $this->relationId(1), $this->bankId(1), new AccountName('Renamed Account')));
        self::assertSame(BankAccountWriteResult::Success, $this->app->make(DeactivateBankAccount::class)->execute($this->admin(self::A), $this->relationId(1), $this->bankId(1)));
        self::assertSame(BankAccountWriteResult::Success, $this->app->make(DeactivateBankAccount::class)->execute($this->admin(self::A), $this->relationId(1), $this->bankId(1)));
        $this->assertDatabaseHas('relation_bank_accounts', ['bank_account_id' => $this->bankId(1)->toString(), 'iban' => 'NL91ABNA0417164300', 'bic' => 'ABNANL2A', 'account_name' => 'Renamed Account', 'active' => false]);
        self::assertSame(BankAccountWriteResult::Success, $this->app->make(ActivateBankAccount::class)->execute($this->admin(self::A), $this->relationId(1), $this->bankId(1)));
        $this->assertDatabaseCount('relation_bank_accounts', 1);
    }

    public function test_duplicate_identity_and_scoped_not_found_are_safe(): void
    {
        $this->create(1, 'NL91ABNA0417164300', null, 'Account');
        self::assertSame(BankAccountWriteResult::DuplicateIdentity, $this->app->make(CreateBankAccount::class)->execute($this->admin(self::A), $this->relationId(2), $this->bankId(1), new Iban('DE89370400440532013000'), null, new AccountName('Other Account')));
        self::assertSame(BankAccountWriteResult::NotFound, $this->app->make(UpdateBankAccount::class)->execute($this->admin(self::A), $this->relationId(2), $this->bankId(1), new AccountName('Hidden Account')));
        self::assertSame(BankAccountWriteResult::NotFound, $this->app->make(DeactivateBankAccount::class)->execute($this->admin(self::B), $this->relationId(1), $this->bankId(1)));
        self::assertNull($this->app->make(BankAccountReadRepository::class)->findForRelation($this->admin(self::A), $this->relationId(2), $this->bankId(1)));
    }

    public function test_composite_fk_rejects_cross_tenant_parent(): void
    {
        $this->expectException(QueryException::class);
        DB::table('relation_bank_accounts')->insert(['bank_account_id' => $this->bankId(9)->toString(), 'administration_id' => self::B, 'relation_id' => $this->relationId(1)->toString(), 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_name' => 'Cross Tenant', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function create(int $sequence, string $iban, ?string $bic, string $name): void
    {
        self::assertSame(BankAccountWriteResult::Success, $this->app->make(CreateBankAccount::class)->execute($this->admin(self::A), $this->relationId(1), $this->bankId($sequence), new Iban($iban), $bic === null ? null : new Bic($bic), new AccountName($name)));
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

    private function bankId(int $sequence): BankAccountId
    {
        return new BankAccountId($this->uuid('9', $sequence));
    }

    private function uuid(string $prefix, int $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $sequence));
    }
}
