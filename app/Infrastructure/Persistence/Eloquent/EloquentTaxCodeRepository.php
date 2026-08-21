<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Fiscal\TaxCodeSelectionItem;
use App\Application\Fiscal\TaxCodeStore;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\TaxCodeRecord;
use DomainException;

final class EloquentTaxCodeRepository implements TaxCodeReadRepository, TaxCodeStore
{
    public function findActiveForAdministrationAndDirection(
        AdministrationId $administrationId,
        TaxPostingDirection $direction,
    ): array {
        return TaxCodeRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('direction', $direction->value)
            ->where('status', TaxCodeStatus::Active->value)
            ->orderBy('code')
            ->orderBy('id')
            ->get()
            ->map(self::hydrate(...))
            ->all();
    }

    public function findByIdForAdministration(
        AdministrationId $administrationId,
        TaxCodeId $taxCodeId,
    ): ?TaxCodeSelectionItem {
        $record = TaxCodeRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('id', $taxCodeId->toString())
            ->first();

        return $record === null ? null : self::hydrate($record);
    }

    public function save(AdministrationId $administrationId, TaxCode $taxCode): void
    {
        $existing = TaxCodeRecord::query()->find($taxCode->id()->toString());

        if ($existing !== null && $existing->getAttribute('administration_id') !== $administrationId->toString()) {
            throw new DomainException('A TaxCode identity belongs to another Administration.');
        }

        TaxCodeRecord::query()->updateOrCreate(
            ['id' => $taxCode->id()->toString()],
            [
                'administration_id' => $administrationId->toString(),
                'code' => $taxCode->code()->toString(),
                'name' => $taxCode->name()->toString(),
                'rate' => $taxCode->rate()->toString(),
                'direction' => $taxCode->direction()->value,
                'status' => $taxCode->status()->value,
            ],
        );
    }

    private static function hydrate(TaxCodeRecord $record): TaxCodeSelectionItem
    {
        return new TaxCodeSelectionItem(
            new TaxCodeId(new Uuid($record->getAttribute('id'))),
            new TaxCodeCode($record->getAttribute('code')),
            new TaxCodeName($record->getAttribute('name')),
            new TaxRate($record->getAttribute('rate')),
            TaxPostingDirection::from($record->getAttribute('direction')),
            TaxCodeStatus::from($record->getAttribute('status')),
        );
    }
}
