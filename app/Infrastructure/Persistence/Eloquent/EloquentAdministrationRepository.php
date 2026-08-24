<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Administration\AdministrationRepository;
use App\Application\Administration\AdministrationSettings;
use App\Application\Administration\AdministrationSettingsReader;
use App\Application\Administration\AdministrationSettingsUpdater;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\Entities\Organisation;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Administration\ValueObjects\OrganisationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;

final class EloquentAdministrationRepository implements AdministrationRepository, AdministrationSettingsReader, AdministrationSettingsUpdater
{
    public function findById(AdministrationId $id): ?Administration
    {
        $record = AdministrationRecord::query()->find($id->toString());

        if ($record === null) {
            return null;
        }

        return new Administration(
            new AdministrationId(new Uuid($record->getAttribute('id'))),
            new AdministrationCode($record->getAttribute('code')),
            new AdministrationName($record->getAttribute('name')),
            $record->getAttribute('description'),
            new Currency($record->getAttribute('base_currency')),
            AdministrationStatus::from($record->getAttribute('status')),
            $this->reconstituteOrganisation($record),
        );
    }

    public function save(Administration $administration): void
    {
        $organisation = $administration->organisation();

        AdministrationRecord::query()->updateOrCreate(
            ['id' => $administration->id()->toString()],
            [
                'code' => $administration->code()->toString(),
                'name' => $administration->name()->toString(),
                'description' => $administration->description(),
                'base_currency' => $administration->baseCurrency()->code(),
                'status' => $administration->status()->value,
                'organisation_id' => $organisation?->id()->toString(),
                'organisation_display_name' => $organisation?->displayName(),
                'organisation_legal_name' => $organisation?->legalName(),
                'organisation_legal_form' => $organisation?->legalForm(),
                'organisation_chamber_of_commerce_number' => $organisation?->chamberOfCommerceNumber(),
                'organisation_vat_number' => $organisation?->vatNumber(),
                'organisation_primary_address' => $organisation?->primaryAddress(),
                'organisation_iban' => $organisation?->iban(),
                'organisation_bic' => $organisation?->bic(),
            ],
        );
    }

    public function findSettings(AdministrationId $administrationId): ?AdministrationSettings
    {
        $record = AdministrationRecord::query()
            ->select(['name', 'description'])
            ->find($administrationId->toString());

        return $record === null ? null : new AdministrationSettings(
            $record->getAttribute('name'),
            $record->getAttribute('description'),
        );
    }

    public function updateSettings(Administration $administration): bool
    {
        $query = AdministrationRecord::query()
            ->whereKey($administration->id()->toString());
        $updated = $query->update([
            'name' => $administration->name()->toString(),
            'description' => $administration->description(),
        ]);

        return $updated === 1 || $query->exists();
    }

    private function reconstituteOrganisation(AdministrationRecord $record): ?Organisation
    {
        $id = $record->getAttribute('organisation_id');

        if ($id === null) {
            return null;
        }

        return new Organisation(
            new OrganisationId(new Uuid($id)),
            $record->getAttribute('organisation_display_name'),
            $record->getAttribute('organisation_legal_name'),
            $record->getAttribute('organisation_legal_form'),
            $record->getAttribute('organisation_chamber_of_commerce_number'),
            $record->getAttribute('organisation_vat_number'),
            $record->getAttribute('organisation_primary_address'),
            $record->getAttribute('organisation_iban'),
            $record->getAttribute('organisation_bic'),
        );
    }
}
