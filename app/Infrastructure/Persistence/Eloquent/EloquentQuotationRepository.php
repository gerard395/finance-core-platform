<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\QuotationCreator;
use App\Application\Sales\QuotationOrderConversionSource;
use App\Application\Sales\QuotationReadRepository;
use App\Application\Sales\QuotationUpdater;
use App\Application\Sales\QuotationWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\QuotationLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\QuotationRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentQuotationRepository implements QuotationCreator, QuotationOrderConversionSource, QuotationReadRepository, QuotationUpdater
{
    public function findForAdministration(AdministrationId $administrationId, QuotationId $quotationId): ?Quotation
    {
        $record = QuotationRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->whereKey($quotationId->toString())
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findLockedForAdministration(AdministrationId $administrationId, QuotationId $quotationId): ?Quotation
    {
        $record = QuotationRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->whereKey($quotationId->toString())
            ->lockForUpdate()
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function create(AdministrationId $administrationId, Quotation $quotation): QuotationWriteResult
    {
        try {
            QuotationRecord::query()->create($this->headerAttributes($administrationId, $quotation));
            $this->insertLines($administrationId, $quotation);
        } catch (QueryException $exception) {
            $conflict = $this->classifyCreateConflict($administrationId, $quotation);
            if ($conflict === null) {
                throw $exception;
            }

            return $conflict;
        }

        return QuotationWriteResult::Success;
    }

    public function update(AdministrationId $administrationId, Quotation $quotation): QuotationWriteResult
    {
        $record = QuotationRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->whereKey($quotation->id()->toString())
            ->lockForUpdate()
            ->first();
        if ($record === null) {
            return QuotationWriteResult::NotFound;
        }
        $this->assertImmutableContext($record, $quotation);

        $attributes = $this->headerAttributes($administrationId, $quotation);
        unset($attributes['id'], $attributes['administration_id'], $attributes['quotation_number'], $attributes['customer_id'], $attributes['customer_relation_id_snapshot'], $attributes['customer_number_snapshot'], $attributes['customer_name_snapshot'], $attributes['currency']);
        $record->fill($attributes)->save();
        $this->syncLines($administrationId, $quotation);

        return QuotationWriteResult::Success;
    }

    private function classifyCreateConflict(AdministrationId $administrationId, Quotation $quotation): ?QuotationWriteResult
    {
        if (QuotationRecord::query()->whereKey($quotation->id()->toString())->exists()) {
            return QuotationWriteResult::DuplicateIdentity;
        }
        if (QuotationRecord::query()->where('administration_id', $administrationId->toString())->where('quotation_number', $quotation->number()->value())->exists()) {
            return QuotationWriteResult::DuplicateNumber;
        }

        return null;
    }

    private function assertImmutableContext(QuotationRecord $record, Quotation $quotation): void
    {
        $snapshot = $quotation->customerSnapshot();
        $address = $quotation->documentAddressSnapshot();
        if ($snapshot === null
            || $record->getAttribute('quotation_number') !== $quotation->number()->value()
            || $record->getAttribute('customer_id') !== $quotation->customerId()->toString()
            || $record->getAttribute('customer_relation_id_snapshot') !== $snapshot->relationId()->toString()
            || $record->getAttribute('customer_number_snapshot') !== $snapshot->customerNumber()->toString()
            || $record->getAttribute('customer_name_snapshot') !== $snapshot->displayName()->toString()
            || $record->getAttribute('currency') !== $quotation->currency()->code()
            || $record->getAttribute('quotation_address_id_snapshot') !== $address?->addressId()->toString()
            || $record->getAttribute('quotation_address_type_snapshot') !== $address?->type()->value
            || $record->getAttribute('quotation_address_line_1_snapshot') !== $address?->addressLine()->value()
            || $record->getAttribute('quotation_address_line_2_snapshot') !== $address?->addressLine2()?->value()
            || $record->getAttribute('quotation_postal_code_snapshot') !== $address?->postalCode()->value()
            || $record->getAttribute('quotation_city_snapshot') !== $address?->city()->value()
            || $record->getAttribute('quotation_country_code_snapshot') !== $address?->countryCode()->value()) {
            throw new DomainException('Quotation immutable context cannot change.');
        }
    }

    /** @return array<string, mixed> */
    private function headerAttributes(AdministrationId $administrationId, Quotation $quotation): array
    {
        $snapshot = $quotation->customerSnapshot();
        if ($snapshot === null) {
            throw new DomainException('Persistent Quotation requires a Customer snapshot.');
        }

        $address = $quotation->documentAddressSnapshot();

        return [
            'id' => $quotation->id()->toString(),
            'administration_id' => $administrationId->toString(),
            'quotation_number' => $quotation->number()->value(),
            'customer_id' => $quotation->customerId()->toString(),
            'customer_relation_id_snapshot' => $snapshot->relationId()->toString(),
            'customer_number_snapshot' => $snapshot->customerNumber()->toString(),
            'customer_name_snapshot' => $snapshot->displayName()->toString(),
            'quotation_address_id_snapshot' => $address?->addressId()->toString(),
            'quotation_address_type_snapshot' => $address?->type()->value,
            'quotation_address_line_1_snapshot' => $address?->addressLine()->value(),
            'quotation_address_line_2_snapshot' => $address?->addressLine2()?->value(),
            'quotation_postal_code_snapshot' => $address?->postalCode()->value(),
            'quotation_city_snapshot' => $address?->city()->value(),
            'quotation_country_code_snapshot' => $address?->countryCode()->value(),
            'currency' => $quotation->currency()->code(),
            'quotation_date' => $quotation->quotationDate()->format('Y-m-d'),
            'expiry_date' => $quotation->expiryDate()?->format('Y-m-d'),
            'status' => $quotation->status()->value,
        ];
    }

    private function insertLines(AdministrationId $administrationId, Quotation $quotation): void
    {
        foreach ($quotation->lines() as $line) {
            QuotationLineRecord::query()->create($this->lineAttributes($administrationId, $quotation, $line));
        }
    }

    private function syncLines(AdministrationId $administrationId, Quotation $quotation): void
    {
        $ids = array_map(static fn (QuotationLine $line): string => $line->id()->toString(), $quotation->lines());
        $query = QuotationLineRecord::query()->where('administration_id', $administrationId->toString())->where('quotation_id', $quotation->id()->toString());
        $ids === [] ? $query->delete() : $query->whereNotIn('id', $ids)->delete();
        foreach ($quotation->lines() as $line) {
            $record = QuotationLineRecord::query()->whereKey($line->id()->toString())->where('administration_id', $administrationId->toString())->where('quotation_id', $quotation->id()->toString())->first();
            if ($record === null) {
                QuotationLineRecord::query()->create($this->lineAttributes($administrationId, $quotation, $line));
            } else {
                $record->fill($this->lineAttributes($administrationId, $quotation, $line))->save();
            }
        }
    }

    /** @return array<string, mixed> */
    private function lineAttributes(AdministrationId $administrationId, Quotation $quotation, QuotationLine $line): array
    {
        return ['id' => $line->id()->toString(), 'administration_id' => $administrationId->toString(), 'quotation_id' => $quotation->id()->toString(), 'description' => $line->description()->value(), 'quantity' => $line->quantity()->value(), 'unit_price_amount' => $line->unitPrice()->amount(), 'currency' => $line->unitPrice()->currency()->code()];
    }

    private function hydrate(QuotationRecord $record): Quotation
    {
        $currency = new Currency($record->getAttribute('currency'));
        $lines = QuotationLineRecord::query()->where('administration_id', $record->getAttribute('administration_id'))->where('quotation_id', $record->getAttribute('id'))->orderBy('id')->get()
            ->map(static fn (QuotationLineRecord $line): QuotationLine => new QuotationLine(new QuotationLineId(new Uuid($line->getAttribute('id'))), new LineDescription($line->getAttribute('description')), new Quantity($line->getAttribute('quantity')), new Money($line->getAttribute('unit_price_amount'), $currency)))->all();
        $expiry = $record->getAttribute('expiry_date');

        return Quotation::reconstitute(
            new QuotationId(new Uuid($record->getAttribute('id'))), new QuotationNumber($record->getAttribute('quotation_number')),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))), new CustomerId(new Uuid($record->getAttribute('customer_id'))), $currency,
            QuotationStatus::from($record->getAttribute('status')), new DateTimeImmutable($record->getAttribute('quotation_date')),
            $expiry === null ? null : new DateTimeImmutable($expiry), $lines,
            new SalesCustomerSnapshot(new CustomerId(new Uuid($record->getAttribute('customer_id'))), new RelationId(new Uuid($record->getAttribute('customer_relation_id_snapshot'))), new CustomerNumber($record->getAttribute('customer_number_snapshot')), new DisplayName($record->getAttribute('customer_name_snapshot'))),
            $this->hydrateDocumentAddress($record),
        );
    }

    private function hydrateDocumentAddress(QuotationRecord $record): ?SalesAddressSnapshot
    {
        $values = [
            $record->getAttribute('quotation_address_id_snapshot'),
            $record->getAttribute('quotation_address_type_snapshot'),
            $record->getAttribute('quotation_address_line_1_snapshot'),
            $record->getAttribute('quotation_postal_code_snapshot'),
            $record->getAttribute('quotation_city_snapshot'),
            $record->getAttribute('quotation_country_code_snapshot'),
        ];
        if (count(array_filter($values, static fn (mixed $value): bool => $value !== null)) === 0) {
            return null;
        }
        if (in_array(null, $values, true)) {
            throw new DomainException('Quotation document address snapshot is incomplete.');
        }
        $line2 = $record->getAttribute('quotation_address_line_2_snapshot');

        return new SalesAddressSnapshot(
            new AddressId(new Uuid($values[0])),
            AddressType::from($values[1]),
            new AddressLine($values[2]),
            $line2 === null ? null : new AddressLine($line2),
            new PostalCode($values[3]),
            new City($values[4]),
            new CountryCode($values[5]),
        );
    }
}
