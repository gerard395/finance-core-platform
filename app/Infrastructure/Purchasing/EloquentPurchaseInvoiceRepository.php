<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseInvoiceRepository;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLedgerAccountRole;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxLegDefinition;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Entities\PurchaseInvoiceLine;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\Enums\PurchaseSupplyClassification;
use App\Domain\Purchasing\ValueObjects\InternationalPurchaseSourceFacts;
use App\Domain\Purchasing\ValueObjects\PurchaseAccountSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseSupplierSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxTreatmentSnapshot;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
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

final class EloquentPurchaseInvoiceRepository implements PurchaseInvoiceRepository
{
    public function create(PurchaseInvoice $invoice): bool
    {
        try {
            $this->insertHeader($invoice);
            $this->replaceLines($invoice);

            return true;
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            } throw $e;
        }
    }

    public function save(PurchaseInvoice $invoice): bool
    {
        try {
            DB::table('purchase_invoices')->where('administration_id', $invoice->administrationId()->toString())->where('id', $invoice->id()->toString())->update($this->header($invoice));
            $this->replaceLines($invoice);

            return true;
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            } throw $e;
        }
    }

    public function find(AdministrationId $admin, PurchaseInvoiceId $id): ?PurchaseInvoice
    {
        $row = DB::table('purchase_invoices')->where('administration_id', $admin->toString())->where('id', $id->toString())->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findForUpdate(AdministrationId $admin, PurchaseInvoiceId $id): ?PurchaseInvoice
    {
        $row = DB::table('purchase_invoices')->where('administration_id', $admin->toString())->where('id', $id->toString())->lockForUpdate()->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function list(AdministrationId $admin): array
    {
        return DB::table('purchase_invoices')->where('administration_id', $admin->toString())->orderByDesc('supplier_invoice_date')->orderBy('id')->get()->map(fn ($row) => $this->hydrate($row))->all();
    }

    private function insertHeader(PurchaseInvoice $invoice): void
    {
        DB::table('purchase_invoices')->insert(['id' => $invoice->id()->toString(), 'administration_id' => $invoice->administrationId()->toString(), ...$this->header($invoice), 'created_at' => now()]);
    }

    private function header(PurchaseInvoice $i): array
    {
        $s = $i->supplierSnapshot();
        $a = $i->documentAddress();

        return ['supplier_id' => $s->supplierId->toString(), 'supplier_relation_id_snapshot' => $s->relationId->toString(), 'supplier_number_snapshot' => $s->supplierNumber->toString(), 'supplier_name_snapshot' => $s->name->toString(), 'supplier_vat_id_snapshot' => $s->vatIdentificationNumber?->toString(), 'supplier_jurisdiction_snapshot' => $s->fiscalJurisdiction?->value(), 'supplier_invoice_number' => $i->supplierInvoiceNumber()->canonical(), 'supplier_invoice_date' => $i->supplierInvoiceDate()->format('Y-m-d'), 'received_date' => $i->receivedDate()->format('Y-m-d'), 'supply_date' => $i->supplyDate()?->format('Y-m-d'), 'fiscal_reporting_date' => $i->fiscalReportingDate()->format('Y-m-d'), 'due_date' => $i->dueDate()->format('Y-m-d'), 'currency' => $i->currency()->code(), 'address_line_1_snapshot' => $a->line1->value(), 'address_line_2_snapshot' => $a->line2?->value(), 'postal_code_snapshot' => $a->postalCode->value(), 'city_snapshot' => $a->city->value(), 'country_code_snapshot' => $a->countryCode->value(), 'status' => $i->status()->value, 'finalized_by' => $i->finalizedBy()?->toString(), 'finalized_at' => $i->finalizedAt()?->format('Y-m-d H:i:s.u'), 'updated_at' => now()];
    }

    private function replaceLines(PurchaseInvoice $i): void
    {
        DB::table('purchase_invoice_lines')->where('administration_id', $i->administrationId()->toString())->where('purchase_invoice_id', $i->id()->toString())->delete();
        foreach ($i->lines() as $line) {
            $a = $line->account();
            $t = $line->tax();
            DB::table('purchase_invoice_lines')->insert(['id' => $line->id()->toString(), 'administration_id' => $i->administrationId()->toString(), 'purchase_invoice_id' => $i->id()->toString(), 'description' => $line->description()->value(), 'quantity' => $line->quantity()->value(), 'unit_price_amount' => $line->unitPrice()->amount(), 'currency' => $line->unitPrice()->currency()->code(), 'ledger_account_id' => $a->id->toString(), 'ledger_account_code_snapshot' => $a->code->value(), 'ledger_account_name_snapshot' => $a->name->value(), 'ledger_account_type_snapshot' => $a->type->value, 'tax_code_id' => $t->id->toString(), 'tax_code_snapshot' => $t->code->value(), 'tax_name_snapshot' => $t->name->value(), 'tax_rate_snapshot' => $t->rate->value(), 'tax_direction_snapshot' => $t->direction->value, 'tax_treatment_snapshot' => $t->treatment->value, 'vat_return_classification_snapshot' => $t->vatReturn->value, 'icp_classification_snapshot' => $t->icp->value, 'net_amount' => $line->net()->amount(), 'tax_amount' => $line->taxAmount()->amount(), 'gross_amount' => $line->gross()->amount(), 'international_tax_input' => $this->sourceFactsJson($line), 'tax_treatment_definition_snapshot' => $this->treatmentSnapshotJson($line), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function hydrate(object $r): PurchaseInvoice
    {
        $currency = new Currency($r->currency);
        $lines = DB::table('purchase_invoice_lines')->where('administration_id', $r->administration_id)->where('purchase_invoice_id', $r->id)->orderBy('id')->get()->map(function ($l) use ($currency) {
            $account = new PurchaseAccountSnapshot(new LedgerAccountId(new Uuid($l->ledger_account_id)), new LedgerAccountCode($l->ledger_account_code_snapshot), new LedgerAccountName($l->ledger_account_name_snapshot), LedgerAccountType::from($l->ledger_account_type_snapshot));
            $tax = new PurchaseTaxSnapshot(new TaxCodeId(new Uuid($l->tax_code_id)), new TaxCodeCode($l->tax_code_snapshot), new TaxCodeName($l->tax_name_snapshot), new TaxRate($l->tax_rate_snapshot), TaxPostingDirection::from($l->tax_direction_snapshot), TaxTreatment::from($l->tax_treatment_snapshot), VatReturnClassification::from($l->vat_return_classification_snapshot), IcpClassification::from($l->icp_classification_snapshot));

            [$deductibility, $facts] = $this->hydrateSourceFacts($l->international_tax_input ?? null);
            $snapshot = $this->hydrateTreatmentSnapshot($l->tax_treatment_definition_snapshot ?? null, $facts, $deductibility);

            return new PurchaseInvoiceLine(new PurchaseInvoiceLineId(new Uuid($l->id)), new LineDescription($l->description), new Quantity($l->quantity), new Money($l->unit_price_amount, $currency), $account, $tax, new Money($l->net_amount, $currency), new Money($l->tax_amount, $currency), new Money($l->gross_amount, $currency), $deductibility, $facts, $snapshot);
        })->all();
        $supplier = new PurchaseSupplierSnapshot(new SupplierId(new Uuid($r->supplier_id)), new RelationId(new Uuid($r->supplier_relation_id_snapshot)), new SupplierNumber($r->supplier_number_snapshot), new DisplayName($r->supplier_name_snapshot), $r->supplier_vat_id_snapshot === null ? null : new VatIdentificationNumber($r->supplier_vat_id_snapshot), $r->supplier_jurisdiction_snapshot === null ? null : new CountryCode($r->supplier_jurisdiction_snapshot));

        return new PurchaseInvoice(new PurchaseInvoiceId(new Uuid($r->id)), new SupplierInvoiceNumber($r->supplier_invoice_number), new AdministrationId(new Uuid($r->administration_id)), $supplier, $currency, new DateTimeImmutable($r->supplier_invoice_date), new DateTimeImmutable($r->received_date), $r->supply_date === null ? null : new DateTimeImmutable($r->supply_date), new DateTimeImmutable($r->fiscal_reporting_date), new DateTimeImmutable($r->due_date), new PurchaseDocumentAddress(new AddressLine($r->address_line_1_snapshot), $r->address_line_2_snapshot === null ? null : new AddressLine($r->address_line_2_snapshot), new PostalCode($r->postal_code_snapshot), new City($r->city_snapshot), new CountryCode($r->country_code_snapshot)), PurchaseInvoiceStatus::from($r->status), $lines, $r->finalized_by === null ? null : new UserId(new Uuid($r->finalized_by)), $r->finalized_at === null ? null : new DateTimeImmutable($r->finalized_at));
    }

    private function sourceFactsJson(PurchaseInvoiceLine $line): ?string
    {
        $f = $line->internationalSourceFacts();
        if ($f === null) {
            return null;
        }

        return json_encode(['deductibility' => $line->deductibility()?->value(), 'supplier_jurisdiction' => $f->supplierJurisdiction, 'customer_jurisdiction' => $f->customerJurisdiction, 'supplier_vat_identity' => $f->supplierVatIdentity, 'customer_vat_identity' => $f->customerVatIdentity, 'classification' => $f->classification->value, 'b2b' => $f->businessToBusiness, 'arrives_nl' => $f->arrivesInNetherlands, 'general_rule' => $f->generalRuleConfirmed, 'special_rule' => $f->specialPlaceOfSupply, 'foreign_vat' => $f->foreignSupplierVat, 'import_customs' => $f->importOrCustoms, 'evidence' => $f->evidence, 'rationale' => $f->deductibilityRationale, 'policy_version' => $f->deductibilityPolicyVersion], JSON_THROW_ON_ERROR);
    }

    private function treatmentSnapshotJson(PurchaseInvoiceLine $line): ?string
    {
        $s = $line->treatmentSnapshot();
        if ($s === null) {
            return null;
        }

        return json_encode(['definition_id' => $s->definitionId->toString(), 'version' => $s->definitionVersion, 'type' => $s->treatmentType->value, 'jurisdiction' => $s->jurisdiction, 'rate' => $s->vatRate->value(), 'supplier_vat_mode' => $s->supplierVatMode->value, 'deductibility_policy' => $s->deductibilityPolicy->value, 'legs' => array_map(static fn (TaxLegDefinition $leg) => ['role' => $leg->role->value, 'direction' => $leg->direction->value, 'reporting' => $leg->reportingClassification->value, 'account_role' => $leg->ledgerAccountRole->value, 'emit_zero' => $leg->emitWhenZero], $s->legDefinitions), 'frozen_by' => $s->frozenBy->toString(), 'frozen_at' => $s->frozenAt->format('Y-m-d H:i:s.u')], JSON_THROW_ON_ERROR);
    }

    /** @return array{?DeductibilityBasisPoints, ?InternationalPurchaseSourceFacts} */
    private function hydrateSourceFacts(?string $json): array
    {
        if ($json === null) {
            return [null, null];
        }
        $v = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return [new DeductibilityBasisPoints($v['deductibility']), new InternationalPurchaseSourceFacts($v['supplier_jurisdiction'], $v['customer_jurisdiction'], $v['supplier_vat_identity'], $v['customer_vat_identity'], PurchaseSupplyClassification::from($v['classification']), $v['b2b'], $v['arrives_nl'], $v['general_rule'], $v['special_rule'], $v['foreign_vat'], $v['import_customs'], $v['evidence'], $v['rationale'], $v['policy_version'])];
    }

    private function hydrateTreatmentSnapshot(?string $json, ?InternationalPurchaseSourceFacts $facts, ?DeductibilityBasisPoints $deductibility): ?PurchaseTaxTreatmentSnapshot
    {
        if ($json === null) {
            return null;
        }
        if ($facts === null || $deductibility === null) {
            throw new \DomainException('International purchase treatment snapshot is incomplete.');
        }
        $v = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $legs = array_map(static fn (array $leg) => new TaxLegDefinition(TaxLegRole::from($leg['role']), TaxPostingDirection::from($leg['direction']), TaxReportingClassification::from($leg['reporting']), TaxLedgerAccountRole::from($leg['account_role']), $leg['emit_zero']), $v['legs']);

        return new PurchaseTaxTreatmentSnapshot(new TaxTreatmentDefinitionId(new Uuid($v['definition_id'])), $v['version'], TaxTreatmentType::from($v['type']), $v['jurisdiction'], new TaxRate($v['rate']), SupplierVatMode::from($v['supplier_vat_mode']), DeductibilityPolicy::from($v['deductibility_policy']), $legs, $deductibility, $facts, new UserId(new Uuid($v['frozen_by'])), new DateTimeImmutable($v['frozen_at']));
    }
}
