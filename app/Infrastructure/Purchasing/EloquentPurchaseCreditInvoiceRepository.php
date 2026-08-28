<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseCreditInvoiceRepository;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoiceLine;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseAccountSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseSupplierSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentPurchaseCreditInvoiceRepository implements PurchaseCreditInvoiceRepository
{
    public function create(PurchaseCreditInvoice $c): bool
    {
        try {
            $this->insertHeader($c);
            $this->replaceLines($c);

            return true;
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            }throw $e;
        }
    }

    public function save(PurchaseCreditInvoice $c): bool
    {
        try {
            DB::table('purchase_credit_invoices')->where('administration_id', $c->administrationId()->toString())->where('id', $c->id()->toString())->update($this->header($c));
            $this->replaceLines($c);

            return true;
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            }throw $e;
        }
    }

    public function find(AdministrationId $a, PurchaseCreditInvoiceId $id): ?PurchaseCreditInvoice
    {
        $r = DB::table('purchase_credit_invoices')->where('administration_id', $a->toString())->where('id', $id->toString())->first();

        return $r === null ? null : $this->hydrate($r);
    }

    public function findForUpdate(AdministrationId $a, PurchaseCreditInvoiceId $id): ?PurchaseCreditInvoice
    {
        $r = DB::table('purchase_credit_invoices')->where('administration_id', $a->toString())->where('id', $id->toString())->lockForUpdate()->first();

        return $r === null ? null : $this->hydrate($r);
    }

    public function list(AdministrationId $a): array
    {
        return DB::table('purchase_credit_invoices')->where('administration_id', $a->toString())->orderByDesc('supplier_credit_date')->orderBy('id')->get()->map(fn ($r) => $this->hydrate($r))->all();
    }

    private function insertHeader(PurchaseCreditInvoice $c): void
    {
        DB::table('purchase_credit_invoices')->insert(['id' => $c->id()->toString(), 'administration_id' => $c->administrationId()->toString(), ...$this->header($c)]);
    }

    private function header(PurchaseCreditInvoice $c): array
    {
        $s = $c->supplierSnapshot();
        $a = $c->documentAddress();

        return ['supplier_id' => $c->supplierId()->toString(), 'supplier_relation_id_snapshot' => $s?->relationId->toString(), 'supplier_number_snapshot' => $s?->supplierNumber->toString(), 'supplier_name_snapshot' => $s?->name->toString(), 'supplier_vat_id_snapshot' => $s?->vatIdentificationNumber?->toString(), 'supplier_jurisdiction_snapshot' => $s?->fiscalJurisdiction?->value(), 'source_purchase_invoice_id' => $c->sourcePurchaseInvoiceId()?->toString(), 'source_payable_open_item_id' => $c->sourcePayableOpenItemId()?->toString(), 'supplier_credit_invoice_number' => $c->number()->canonical(), 'supplier_credit_date' => $c->supplierCreditDate()->format('Y-m-d'), 'received_date' => $c->receivedDate()->format('Y-m-d'), 'fiscal_reporting_date' => $c->fiscalReportingDate()->format('Y-m-d'), 'source_supply_date' => $c->sourceSupplyDate()?->format('Y-m-d'), 'currency' => $c->currency()->code(), 'address_line_1_snapshot' => $a?->line1->value(), 'address_line_2_snapshot' => $a?->line2?->value(), 'postal_code_snapshot' => $a?->postalCode->value(), 'city_snapshot' => $a?->city->value(), 'country_code_snapshot' => $a?->countryCode->value(), 'status' => $c->status()->value, 'created_by' => $c->createdBy()?->toString(), 'created_at' => $c->createdAt()?->format('Y-m-d H:i:s.u'), 'finalized_by' => $c->finalizedBy()?->toString(), 'finalized_at' => $c->finalizedAt()?->format('Y-m-d H:i:s.u'), 'cancelled_by' => $c->cancelledBy()?->toString(), 'cancelled_at' => $c->cancelledAt()?->format('Y-m-d H:i:s.u'), 'updated_at' => now()];
    }

    private function replaceLines(PurchaseCreditInvoice $c): void
    {
        DB::table('purchase_credit_invoice_lines')->where('administration_id', $c->administrationId()->toString())->where('purchase_credit_invoice_id', $c->id()->toString())->delete();
        foreach ($c->lines() as $l) {
            $a = $l->account();
            $t = $l->tax();
            DB::table('purchase_credit_invoice_lines')->insert([
                'id' => $l->id()->toString(), 'administration_id' => $c->administrationId()->toString(),
                'purchase_credit_invoice_id' => $c->id()->toString(), 'source_purchase_invoice_id' => $c->sourcePurchaseInvoiceId()?->toString(),
                'source_purchase_invoice_line_id' => $l->sourcePurchaseInvoiceLineId()?->toString(), 'source_tax_posting_id' => $l->sourceTaxPostingId()?->toString(),
                'description' => $l->description()->value(), 'quantity' => $l->quantity()->value(), 'unit_price_amount' => $l->unitPrice()->amount(), 'currency' => $c->currency()->code(),
                'ledger_account_id' => $a?->id->toString(), 'ledger_account_code_snapshot' => $a?->code->value(), 'ledger_account_name_snapshot' => $a?->name->value(), 'ledger_account_type_snapshot' => $a?->type->value,
                'tax_code_id' => $t?->id->toString(), 'tax_code_snapshot' => $t?->code->value(), 'tax_name_snapshot' => $t?->name->value(), 'tax_rate_snapshot' => $t?->rate->value(),
                'tax_direction_snapshot' => $t?->direction->value, 'tax_treatment_snapshot' => $t?->treatment->value, 'vat_return_classification_snapshot' => $t?->vatReturn->value, 'icp_classification_snapshot' => $t?->icp->value,
                'net_amount' => $l->net()->amount(), 'taxable_base' => $l->net()->amount(), 'tax_amount' => $l->taxAmount()->amount(), 'gross_amount' => $l->gross()->amount(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function hydrate(object $r): PurchaseCreditInvoice
    {
        $cur = new Currency($r->currency);
        $lines = DB::table('purchase_credit_invoice_lines')->where('administration_id', $r->administration_id)->where('purchase_credit_invoice_id', $r->id)->orderBy('id')->get()->map(function ($l) use ($cur) {
            $a = new PurchaseAccountSnapshot(new LedgerAccountId(new Uuid($l->ledger_account_id)), new LedgerAccountCode($l->ledger_account_code_snapshot), new LedgerAccountName($l->ledger_account_name_snapshot), LedgerAccountType::from($l->ledger_account_type_snapshot));
            $t = new PurchaseTaxSnapshot(new TaxCodeId(new Uuid($l->tax_code_id)), new TaxCodeCode($l->tax_code_snapshot), new TaxCodeName($l->tax_name_snapshot), new TaxRate($l->tax_rate_snapshot), TaxPostingDirection::from($l->tax_direction_snapshot), TaxTreatment::from($l->tax_treatment_snapshot), VatReturnClassification::from($l->vat_return_classification_snapshot), IcpClassification::from($l->icp_classification_snapshot));
            $net = new Money($l->net_amount, $cur);

            return new PurchaseCreditInvoiceLine(new PurchaseCreditInvoiceLineId(new Uuid($l->id)), new LineDescription($l->description), new Quantity($l->quantity), new Money($l->unit_price_amount, $cur), new PurchaseInvoiceLineId(new Uuid($l->source_purchase_invoice_line_id)), $a, $t, $net, new Money($l->tax_amount, $cur), new Money($l->gross_amount, $cur), $l->source_tax_posting_id === null ? null : new TaxPostingId(new Uuid($l->source_tax_posting_id)));
        })->all();
        $s = new PurchaseSupplierSnapshot(new SupplierId(new Uuid($r->supplier_id)), new RelationId(new Uuid($r->supplier_relation_id_snapshot)), new SupplierNumber($r->supplier_number_snapshot), new DisplayName($r->supplier_name_snapshot), $r->supplier_vat_id_snapshot === null ? null : new VatIdentificationNumber($r->supplier_vat_id_snapshot), $r->supplier_jurisdiction_snapshot === null ? null : new CountryCode($r->supplier_jurisdiction_snapshot));
        $a = new PurchaseDocumentAddress(new AddressLine($r->address_line_1_snapshot), $r->address_line_2_snapshot === null ? null : new AddressLine($r->address_line_2_snapshot), new PostalCode($r->postal_code_snapshot), new City($r->city_snapshot), new CountryCode($r->country_code_snapshot));

        return new PurchaseCreditInvoice(new PurchaseCreditInvoiceId(new Uuid($r->id)), new PurchaseCreditInvoiceNumber($r->supplier_credit_invoice_number), new AdministrationId(new Uuid($r->administration_id)), new SupplierId(new Uuid($r->supplier_id)), $cur, new DateTimeImmutable($r->supplier_credit_date), new PurchaseInvoiceId(new Uuid($r->source_purchase_invoice_id)), PurchaseCreditInvoiceStatus::from($r->status), $s, $a, new DateTimeImmutable($r->received_date), new DateTimeImmutable($r->fiscal_reporting_date), $r->source_supply_date === null ? null : new DateTimeImmutable($r->source_supply_date), new OpenItemId(new Uuid($r->source_payable_open_item_id)), new UserId(new Uuid($r->created_by)), new DateTimeImmutable($r->created_at), $lines, $r->finalized_by === null ? null : new UserId(new Uuid($r->finalized_by)), $r->finalized_at === null ? null : new DateTimeImmutable($r->finalized_at), $r->cancelled_by === null ? null : new UserId(new Uuid($r->cancelled_by)), $r->cancelled_at === null ? null : new DateTimeImmutable($r->cancelled_at));
    }
}
