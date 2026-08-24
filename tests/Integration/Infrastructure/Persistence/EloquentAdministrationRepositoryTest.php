<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Administration\AdministrationRepository;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\Entities\Organisation;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Administration\ValueObjects\OrganisationId;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentAdministrationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_and_reconstitutes_an_administration_with_organisation(): void
    {
        $repository = new EloquentAdministrationRepository;
        $administration = $this->administration();

        $repository->save($administration);

        $reconstituted = $repository->findById($administration->id());

        self::assertInstanceOf(Administration::class, $reconstituted);
        self::assertNotInstanceOf(AdministrationRecord::class, $reconstituted);
        self::assertTrue($administration->id()->equals($reconstituted->id()));
        self::assertSame('NL-2026', $reconstituted->code()->toString());
        self::assertSame('Nederland 2026', $reconstituted->name()->toString());
        self::assertSame('Primary administration', $reconstituted->description());
        self::assertSame('EUR', $reconstituted->baseCurrency()->code());
        self::assertSame(AdministrationStatus::Active, $reconstituted->status());
        self::assertSame('Finance Core B.V.', $reconstituted->organisation()?->displayName());
        self::assertSame('12345678', $reconstituted->organisation()?->chamberOfCommerceNumber());
        self::assertSame('NL00BANK0123456789', $reconstituted->organisation()?->iban());
        self::assertSame('NL123456789B01', $reconstituted->vatIdentificationNumber()?->toString());
        self::assertNull($reconstituted->fiscalJurisdiction());
    }

    public function test_fiscal_master_data_roundtrips_and_nullable_existing_data_remains_supported(): void
    {
        $repository = new EloquentAdministrationRepository;
        $administration = $this->administration();
        $administration->changeFiscalMasterData(new VatIdentificationNumber('be0123456789'), new CountryCode('be'));
        $repository->save($administration);

        $fiscalParty = $repository->findFiscalParty($administration->id());
        self::assertSame('BE0123456789', $fiscalParty?->vatIdentificationNumber?->toString());
        self::assertSame('BE', $fiscalParty?->fiscalJurisdiction?->value());

        $administration->changeFiscalMasterData(null, null);
        $repository->save($administration);
        self::assertNull($repository->findById($administration->id())?->vatIdentificationNumber());
        self::assertNull($repository->findById($administration->id())?->fiscalJurisdiction());
    }

    public function test_save_updates_an_existing_administration_without_duplication(): void
    {
        $repository = new EloquentAdministrationRepository;
        $administration = $this->administration();
        $repository->save($administration);

        $administration->rename(new AdministrationName('Updated Administration'));
        $administration->deactivate();
        $repository->save($administration);

        self::assertSame(1, AdministrationRecord::query()->count());
        self::assertSame('Updated Administration', $repository->findById($administration->id())?->name()->toString());
        self::assertSame(AdministrationStatus::Inactive, $repository->findById($administration->id())?->status());
    }

    public function test_find_by_id_returns_null_for_an_unknown_administration(): void
    {
        $repository = new EloquentAdministrationRepository;

        self::assertNull($repository->findById($this->administration()->id()));
    }

    public function test_application_contract_resolves_to_the_eloquent_adapter(): void
    {
        self::assertInstanceOf(
            EloquentAdministrationRepository::class,
            $this->app->make(AdministrationRepository::class),
        );
    }

    private function administration(): Administration
    {
        return new Administration(
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new AdministrationCode('nl-2026'),
            new AdministrationName('Nederland 2026'),
            'Primary administration',
            new Currency('EUR'),
            AdministrationStatus::Active,
            new Organisation(
                new OrganisationId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
                'Finance Core B.V.',
                'Finance Core Platform B.V.',
                'Besloten vennootschap',
                '12345678',
                'NL123456789B01',
                'Damrak 1, Amsterdam',
                'NL00BANK0123456789',
                'BANKNL2A',
            ),
        );
    }
}
