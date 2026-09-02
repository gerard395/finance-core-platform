<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Fiscal\TaxCodeSelectionItem;
use App\Application\Purchasing\PurchaseInvoiceMasterDataReader;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Purchasing\ValueObjects\PurchaseSupplierSnapshot;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentLedgerAccountRepository;
use Illuminate\Support\Facades\DB;

final readonly class EloquentPurchaseInvoiceMasterDataReader implements PurchaseInvoiceMasterDataReader
{
    public function __construct(private EloquentLedgerAccountRepository $accounts, private TaxCodeReadRepository $taxCodes) {}

    public function supplierExists(AdministrationId $administrationId, SupplierId $supplierId): bool
    {
        return DB::table('suppliers')
            ->where('administration_id', $administrationId->toString())
            ->where('id', $supplierId->toString())
            ->exists();
    }

    public function activeSupplier(AdministrationId $admin, SupplierId $id): ?PurchaseSupplierSnapshot
    {
        $row = DB::table('suppliers')->join('relations', fn ($join) => $join->on('relations.administration_id', '=', 'suppliers.administration_id')->on('relations.id', '=', 'suppliers.relation_id'))->where('suppliers.administration_id', $admin->toString())->where('suppliers.id', $id->toString())->where('suppliers.active', true)->first(['suppliers.id', 'suppliers.relation_id', 'suppliers.supplier_number', 'relations.display_name', 'relations.vat_identification_number', 'relations.fiscal_jurisdiction']);

        return $row === null ? null : new PurchaseSupplierSnapshot(new SupplierId(new Uuid($row->id)), new RelationId(new Uuid($row->relation_id)), new SupplierNumber($row->supplier_number), new DisplayName($row->display_name), $row->vat_identification_number === null ? null : new VatIdentificationNumber($row->vat_identification_number), $row->fiscal_jurisdiction === null ? null : new CountryCode($row->fiscal_jurisdiction));
    }

    public function activeLineAccount(AdministrationId $admin, LedgerAccountId $id): ?LedgerAccount
    {
        foreach ($this->activeLineAccounts($admin) as $account) {
            if ($account->id()->equals($id)) {
                return $account;
            }
        }

        return null;
    }

    public function activeLedgerAccount(AdministrationId $admin, LedgerAccountId $id): ?LedgerAccount
    {
        foreach ($this->accounts->findForAdministration($admin) as $account) {
            if ($account->id()->equals($id) && $account->status() === LedgerAccountStatus::Active) {
                return $account;
            }
        }

        return null;
    }

    public function activeInputTaxCode(AdministrationId $admin, TaxCodeId $id): ?TaxCodeSelectionItem
    {
        $item = $this->taxCodes->findByIdForAdministration($admin, $id);

        return $item !== null && $item->status()->value === 'active' && $item->direction() === TaxPostingDirection::Input ? $item : null;
    }

    public function activeTaxCode(AdministrationId $admin, TaxCodeId $id): ?TaxCodeSelectionItem
    {
        $item = $this->taxCodes->findByIdForAdministration($admin, $id);

        return $item !== null && $item->status()->value === 'active' ? $item : null;
    }

    public function activeSuppliers(AdministrationId $admin): array
    {
        return DB::table('suppliers')->where('administration_id', $admin->toString())->where('active', true)->orderBy('supplier_number')->pluck('id')->map(fn ($id) => $this->activeSupplier($admin, new SupplierId(new Uuid($id))))->filter()->values()->all();
    }

    public function activeLineAccounts(AdministrationId $admin): array
    {
        return array_values(array_filter($this->accounts->findForAdministration($admin), fn ($a) => $a->status() === LedgerAccountStatus::Active && in_array($a->type(), [LedgerAccountType::Expense, LedgerAccountType::Asset], true)));
    }

    public function activeInputTaxCodes(AdministrationId $admin): array
    {
        return $this->taxCodes->findActiveForAdministrationAndDirection($admin, TaxPostingDirection::Input);
    }

    public function activePurchaseTaxCodes(AdministrationId $admin): array
    {
        $items = [];
        foreach ($this->activeInputTaxCodes($admin) as $item) {
            $items[$item->id()->toString()] = $item;
        }
        foreach ($this->internationalTaxCodeIds($admin) as $id) {
            $item = $this->activeTaxCode($admin, $id);
            if ($item !== null) {
                $items[$id->toString()] = $item;
            }
        }
        ksort($items);

        return array_values($items);
    }

    public function internationalTaxCodeIds(AdministrationId $admin): array
    {
        return DB::table('tax_treatment_definitions')
            ->where('administration_id', $admin->toString())
            ->where('active', true)
            ->distinct()
            ->orderBy('tax_code_id')
            ->pluck('tax_code_id')
            ->map(static fn (string $id): TaxCodeId => new TaxCodeId(new Uuid($id)))
            ->all();
    }
}
