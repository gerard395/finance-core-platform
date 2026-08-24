<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\SalesCreditInvoiceCreator;
use App\Application\Sales\SalesCreditInvoicePostingSource;
use App\Application\Sales\SalesCreditInvoiceReadRepository;
use App\Application\Sales\SalesCreditInvoiceUpdater;
use App\Application\Sales\SalesCreditInvoiceWriteResult;
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
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesCreditInvoiceLine;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoiceLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoiceRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentSalesCreditInvoiceRepository implements SalesCreditInvoiceCreator, SalesCreditInvoicePostingSource, SalesCreditInvoiceReadRepository, SalesCreditInvoiceUpdater
{
    public function findForAdministration(AdministrationId $administrationId, SalesCreditInvoiceId $invoiceId): ?SalesCreditInvoice
    {
        $record = SalesCreditInvoiceRecord::query()->where('administration_id', $administrationId->toString())->whereKey($invoiceId->toString())->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findLockedForAdministration(AdministrationId $administrationId, SalesCreditInvoiceId $creditInvoiceId): ?SalesCreditInvoice
    {
        $record = SalesCreditInvoiceRecord::query()->where('administration_id', $administrationId->toString())->whereKey($creditInvoiceId->toString())->lockForUpdate()->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function create(AdministrationId $administrationId, SalesCreditInvoice $invoice): SalesCreditInvoiceWriteResult
    {
        try {
            SalesCreditInvoiceRecord::query()->create($this->header($administrationId, $invoice));
            $this->insertLines($administrationId, $invoice);
        } catch (QueryException $exception) {
            if (SalesCreditInvoiceRecord::query()->whereKey($invoice->id()->toString())->exists()) {
                return SalesCreditInvoiceWriteResult::DuplicateIdentity;
            }
            if (SalesCreditInvoiceRecord::query()->where('administration_id', $administrationId->toString())->where('sales_credit_invoice_number', $invoice->number()->value())->exists()) {
                return SalesCreditInvoiceWriteResult::DuplicateNumber;
            }
            if (SalesCreditInvoiceRecord::query()->where('administration_id', $administrationId->toString())->where('source_sales_invoice_id', $invoice->sourceInvoiceId()->toString())->exists()) {
                return SalesCreditInvoiceWriteResult::AlreadyCredited;
            }
            throw $exception;
        }

        return SalesCreditInvoiceWriteResult::Success;
    }

    public function update(AdministrationId $administrationId, SalesCreditInvoice $invoice): SalesCreditInvoiceWriteResult
    {
        $record = SalesCreditInvoiceRecord::query()->where('administration_id', $administrationId->toString())->whereKey($invoice->id()->toString())->lockForUpdate()->first();
        if ($record === null) {
            return SalesCreditInvoiceWriteResult::NotFound;
        }
        $expected = $this->header($administrationId, $invoice);
        foreach (['sales_credit_invoice_number', 'source_sales_invoice_id', 'customer_id', 'customer_relation_id_snapshot', 'customer_number_snapshot', 'customer_name_snapshot', 'invoice_address_id_snapshot', 'invoice_address_type_snapshot', 'invoice_address_line_1_snapshot', 'invoice_address_line_2_snapshot', 'invoice_postal_code_snapshot', 'invoice_city_snapshot', 'invoice_country_code_snapshot', 'currency'] as $field) {
            if ($record->getAttribute($field) !== $expected[$field]) {
                throw new DomainException('SalesCreditInvoice immutable context cannot change.');
            }
        }
        $record->fill(['credit_invoice_date' => $expected['credit_invoice_date'], 'status' => $expected['status']])->save();

        return SalesCreditInvoiceWriteResult::Success;
    }

    /** @return array<string, mixed> */
    private function header(AdministrationId $administrationId, SalesCreditInvoice $invoice): array
    {
        $customer = $invoice->customerSnapshot();
        $address = $invoice->invoiceAddressSnapshot();
        if ($customer === null || $address === null || ! $invoice->administrationId()->equals($administrationId)) {
            throw new DomainException('Persistent SalesCreditInvoice requires matching tenant and complete snapshots.');
        }

        return ['id' => $invoice->id()->toString(), 'administration_id' => $administrationId->toString(), 'sales_credit_invoice_number' => $invoice->number()->value(), 'source_sales_invoice_id' => $invoice->sourceInvoiceId()->toString(), 'customer_id' => $invoice->customerId()->toString(), 'customer_relation_id_snapshot' => $customer->relationId()->toString(), 'customer_number_snapshot' => $customer->customerNumber()->toString(), 'customer_name_snapshot' => $customer->displayName()->toString(), 'invoice_address_id_snapshot' => $address->addressId()->toString(), 'invoice_address_type_snapshot' => $address->type()->value, 'invoice_address_line_1_snapshot' => $address->addressLine()->value(), 'invoice_address_line_2_snapshot' => $address->addressLine2()?->value(), 'invoice_postal_code_snapshot' => $address->postalCode()->value(), 'invoice_city_snapshot' => $address->city()->value(), 'invoice_country_code_snapshot' => $address->countryCode()->value(), 'currency' => $invoice->currency()->code(), 'credit_invoice_date' => $invoice->creditInvoiceDate()->format('Y-m-d'), 'status' => $invoice->status()->value];
    }

    private function insertLines(AdministrationId $administrationId, SalesCreditInvoice $invoice): void
    {
        foreach ($invoice->lines() as $line) {
            SalesCreditInvoiceLineRecord::query()->create(['id' => $line->id()->toString(), 'administration_id' => $administrationId->toString(), 'sales_credit_invoice_id' => $invoice->id()->toString(), 'description' => $line->description()->value(), 'quantity' => $line->quantity()->value(), 'unit_price_amount' => $line->unitPrice()->amount(), 'currency' => $line->unitPrice()->currency()->code()]);
        }
    }

    private function hydrate(SalesCreditInvoiceRecord $record): SalesCreditInvoice
    {
        $currency = new Currency($record->getAttribute('currency'));
        $lines = SalesCreditInvoiceLineRecord::query()->where('administration_id', $record->getAttribute('administration_id'))->where('sales_credit_invoice_id', $record->getAttribute('id'))->orderBy('id')->get()->map(static fn ($line) => new SalesCreditInvoiceLine(new SalesCreditInvoiceLineId(new Uuid($line->getAttribute('id'))), new LineDescription($line->getAttribute('description')), new Quantity($line->getAttribute('quantity')), new Money($line->getAttribute('unit_price_amount'), new Currency($line->getAttribute('currency')))))->all();

        return SalesCreditInvoice::reconstitute(new SalesCreditInvoiceId(new Uuid($record->getAttribute('id'))), new SalesCreditInvoiceNumber($record->getAttribute('sales_credit_invoice_number')), new AdministrationId(new Uuid($record->getAttribute('administration_id'))), new CustomerId(new Uuid($record->getAttribute('customer_id'))), $currency, new DateTimeImmutable($record->getAttribute('credit_invoice_date')), new SalesInvoiceId(new Uuid($record->getAttribute('source_sales_invoice_id'))), SalesCreditInvoiceStatus::from($record->getAttribute('status')), $lines, new SalesCustomerSnapshot(new CustomerId(new Uuid($record->getAttribute('customer_id'))), new RelationId(new Uuid($record->getAttribute('customer_relation_id_snapshot'))), new CustomerNumber($record->getAttribute('customer_number_snapshot')), new DisplayName($record->getAttribute('customer_name_snapshot'))), new SalesAddressSnapshot(new AddressId(new Uuid($record->getAttribute('invoice_address_id_snapshot'))), AddressType::from($record->getAttribute('invoice_address_type_snapshot')), new AddressLine($record->getAttribute('invoice_address_line_1_snapshot')), $record->getAttribute('invoice_address_line_2_snapshot') === null ? null : new AddressLine($record->getAttribute('invoice_address_line_2_snapshot')), new PostalCode($record->getAttribute('invoice_postal_code_snapshot')), new City($record->getAttribute('invoice_city_snapshot')), new CountryCode($record->getAttribute('invoice_country_code_snapshot'))));
    }
}
