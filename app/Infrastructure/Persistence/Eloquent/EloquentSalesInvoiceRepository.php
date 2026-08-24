<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\SalesInvoiceCreator;
use App\Application\Sales\SalesInvoicePostingSource;
use App\Application\Sales\SalesInvoiceReadRepository;
use App\Application\Sales\SalesInvoiceUpdater;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
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
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesTaxSnapshot;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentSalesInvoiceRepository implements SalesInvoiceCreator, SalesInvoicePostingSource, SalesInvoiceReadRepository, SalesInvoiceUpdater
{
    public function findForAdministration(AdministrationId $administrationId, SalesInvoiceId $invoiceId): ?SalesInvoice
    {
        $record = SalesInvoiceRecord::query()->where('administration_id', $administrationId->toString())->whereKey($invoiceId->toString())->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findLockedForAdministration(AdministrationId $administrationId, SalesInvoiceId $invoiceId): ?SalesInvoice
    {
        $record = SalesInvoiceRecord::query()->where('administration_id', $administrationId->toString())->whereKey($invoiceId->toString())->lockForUpdate()->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function create(AdministrationId $administrationId, SalesInvoice $invoice): SalesInvoiceWriteResult
    {
        try {
            SalesInvoiceRecord::query()->create($this->headerAttributes($administrationId, $invoice));
            $this->insertLines($administrationId, $invoice);
        } catch (QueryException $exception) {
            $conflict = $this->classifyCreateConflict($administrationId, $invoice);
            if ($conflict === null) {
                throw $exception;
            }

            return $conflict;
        }

        return SalesInvoiceWriteResult::Success;
    }

    public function update(AdministrationId $administrationId, SalesInvoice $invoice): SalesInvoiceWriteResult
    {
        $record = SalesInvoiceRecord::query()->where('administration_id', $administrationId->toString())->whereKey($invoice->id()->toString())->lockForUpdate()->first();
        if ($record === null) {
            return SalesInvoiceWriteResult::NotFound;
        }
        $this->assertImmutableContext($record, $invoice);
        $attributes = $this->headerAttributes($administrationId, $invoice);
        unset($attributes['id'], $attributes['administration_id'], $attributes['sales_invoice_number'], $attributes['customer_id'], $attributes['customer_relation_id_snapshot'], $attributes['customer_number_snapshot'], $attributes['customer_name_snapshot'], $attributes['invoice_address_id_snapshot'], $attributes['invoice_address_type_snapshot'], $attributes['invoice_address_line_1_snapshot'], $attributes['invoice_address_line_2_snapshot'], $attributes['invoice_postal_code_snapshot'], $attributes['invoice_city_snapshot'], $attributes['invoice_country_code_snapshot'], $attributes['source_order_id'], $attributes['currency']);
        try {
            $record->fill($attributes)->save();
            $this->syncLines($administrationId, $invoice);
        } catch (QueryException) {
            return SalesInvoiceWriteResult::InvalidState;
        }

        return SalesInvoiceWriteResult::Success;
    }

    private function classifyCreateConflict(AdministrationId $administrationId, SalesInvoice $invoice): ?SalesInvoiceWriteResult
    {
        if (SalesInvoiceRecord::query()->whereKey($invoice->id()->toString())->exists()) {
            return SalesInvoiceWriteResult::DuplicateIdentity;
        }
        if (SalesInvoiceRecord::query()->where('administration_id', $administrationId->toString())->where('sales_invoice_number', $invoice->number()->value())->exists()) {
            return SalesInvoiceWriteResult::DuplicateNumber;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function headerAttributes(AdministrationId $administrationId, SalesInvoice $invoice): array
    {
        $customer = $invoice->customerSnapshot();
        $address = $invoice->invoiceAddressSnapshot();
        if ($customer === null || $address === null || ! $invoice->administrationId()->equals($administrationId)) {
            throw new DomainException('Persistent SalesInvoice requires matching tenant and complete snapshots.');
        }

        return [
            'id' => $invoice->id()->toString(), 'administration_id' => $administrationId->toString(), 'sales_invoice_number' => $invoice->number()->value(), 'customer_id' => $invoice->customerId()->toString(),
            'customer_relation_id_snapshot' => $customer->relationId()->toString(), 'customer_number_snapshot' => $customer->customerNumber()->toString(), 'customer_name_snapshot' => $customer->displayName()->toString(),
            'invoice_address_id_snapshot' => $address->addressId()->toString(), 'invoice_address_type_snapshot' => $address->type()->value, 'invoice_address_line_1_snapshot' => $address->addressLine()->value(), 'invoice_address_line_2_snapshot' => $address->addressLine2()?->value(), 'invoice_postal_code_snapshot' => $address->postalCode()->value(), 'invoice_city_snapshot' => $address->city()->value(), 'invoice_country_code_snapshot' => $address->countryCode()->value(),
            'source_order_id' => $invoice->sourceOrderId()?->toString(), 'currency' => $invoice->currency()->code(), 'invoice_date' => $invoice->invoiceDate()->format('Y-m-d'), 'due_date' => $invoice->dueDate()->format('Y-m-d'), 'status' => $invoice->status()->value,
        ];
    }

    private function assertImmutableContext(SalesInvoiceRecord $record, SalesInvoice $invoice): void
    {
        $expected = $this->headerAttributes($invoice->administrationId(), $invoice);
        foreach (['sales_invoice_number', 'customer_id', 'customer_relation_id_snapshot', 'customer_number_snapshot', 'customer_name_snapshot', 'invoice_address_id_snapshot', 'invoice_address_type_snapshot', 'invoice_address_line_1_snapshot', 'invoice_address_line_2_snapshot', 'invoice_postal_code_snapshot', 'invoice_city_snapshot', 'invoice_country_code_snapshot', 'source_order_id', 'currency'] as $field) {
            if ($record->getAttribute($field) !== $expected[$field]) {
                throw new DomainException('SalesInvoice immutable context cannot change.');
            }
        }
    }

    private function insertLines(AdministrationId $administrationId, SalesInvoice $invoice): void
    {
        foreach ($invoice->lines() as $line) {
            SalesInvoiceLineRecord::query()->create($this->lineAttributes($administrationId, $invoice, $line));
        }
    }

    private function syncLines(AdministrationId $administrationId, SalesInvoice $invoice): void
    {
        $ids = array_map(static fn (SalesInvoiceLine $line): string => $line->id()->toString(), $invoice->lines());
        $query = SalesInvoiceLineRecord::query()->where('administration_id', $administrationId->toString())->where('sales_invoice_id', $invoice->id()->toString());
        $ids === [] ? $query->delete() : $query->whereNotIn('id', $ids)->delete();
        foreach ($invoice->lines() as $line) {
            $record = SalesInvoiceLineRecord::query()->whereKey($line->id()->toString())->where('administration_id', $administrationId->toString())->where('sales_invoice_id', $invoice->id()->toString())->first();
            $attributes = $this->lineAttributes($administrationId, $invoice, $line);
            $record === null ? SalesInvoiceLineRecord::query()->create($attributes) : $record->fill($attributes)->save();
        }
    }

    /** @return array<string, mixed> */
    private function lineAttributes(AdministrationId $administrationId, SalesInvoice $invoice, SalesInvoiceLine $line): array
    {
        $tax = $line->taxSnapshot();
        if ($tax === null) {
            throw new DomainException('Persistent SalesInvoice line requires a tax snapshot.');
        }

        return ['id' => $line->id()->toString(), 'administration_id' => $administrationId->toString(), 'sales_invoice_id' => $invoice->id()->toString(), 'description' => $line->description()->value(), 'quantity' => $line->quantity()->value(), 'unit_price_amount' => $line->unitPrice()->amount(), 'currency' => $line->unitPrice()->currency()->code(), 'tax_code_id_snapshot' => $tax->taxCodeId()->toString(), 'tax_code_snapshot' => $tax->taxCode()->value(), 'tax_name_snapshot' => $tax->taxCodeName()->value(), 'tax_rate_snapshot' => $tax->taxRate()->value(), 'tax_direction_snapshot' => $tax->direction()->value];
    }

    private function hydrate(SalesInvoiceRecord $record): SalesInvoice
    {
        $currency = new Currency($record->getAttribute('currency'));
        $lines = SalesInvoiceLineRecord::query()->where('administration_id', $record->getAttribute('administration_id'))->where('sales_invoice_id', $record->getAttribute('id'))->orderBy('id')->get()->map(static fn (SalesInvoiceLineRecord $line): SalesInvoiceLine => new SalesInvoiceLine(new SalesInvoiceLineId(new Uuid($line->getAttribute('id'))), new LineDescription($line->getAttribute('description')), new Quantity($line->getAttribute('quantity')), new Money($line->getAttribute('unit_price_amount'), new Currency($line->getAttribute('currency'))), new SalesTaxSnapshot(new TaxCodeId(new Uuid($line->getAttribute('tax_code_id_snapshot'))), new TaxCodeCode($line->getAttribute('tax_code_snapshot')), new TaxCodeName($line->getAttribute('tax_name_snapshot')), new TaxRate($line->getAttribute('tax_rate_snapshot')), TaxPostingDirection::from($line->getAttribute('tax_direction_snapshot')))))->all();
        $source = $record->getAttribute('source_order_id');

        return SalesInvoice::reconstitute(
            new SalesInvoiceId(new Uuid($record->getAttribute('id'))), new SalesInvoiceNumber($record->getAttribute('sales_invoice_number')), new AdministrationId(new Uuid($record->getAttribute('administration_id'))), new CustomerId(new Uuid($record->getAttribute('customer_id'))), $currency,
            new DateTimeImmutable($record->getAttribute('invoice_date')), new DateTimeImmutable($record->getAttribute('due_date')), $source === null ? null : new OrderId(new Uuid($source)), SalesInvoiceStatus::from($record->getAttribute('status')), $lines,
            new SalesCustomerSnapshot(new CustomerId(new Uuid($record->getAttribute('customer_id'))), new RelationId(new Uuid($record->getAttribute('customer_relation_id_snapshot'))), new CustomerNumber($record->getAttribute('customer_number_snapshot')), new DisplayName($record->getAttribute('customer_name_snapshot'))),
            new SalesAddressSnapshot(new AddressId(new Uuid($record->getAttribute('invoice_address_id_snapshot'))), AddressType::from($record->getAttribute('invoice_address_type_snapshot')), new AddressLine($record->getAttribute('invoice_address_line_1_snapshot')), $record->getAttribute('invoice_address_line_2_snapshot') === null ? null : new AddressLine($record->getAttribute('invoice_address_line_2_snapshot')), new PostalCode($record->getAttribute('invoice_postal_code_snapshot')), new City($record->getAttribute('invoice_city_snapshot')), new CountryCode($record->getAttribute('invoice_country_code_snapshot'))),
        );
    }
}
