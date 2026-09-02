<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Fiscal\TaxCodeSelectionItem;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Purchasing\ValueObjects\PurchaseSupplierSnapshot;
use App\Domain\Relations\ValueObjects\SupplierId;

interface PurchaseInvoiceMasterDataReader
{
    public function supplierExists(AdministrationId $administrationId, SupplierId $supplierId): bool;

    public function activeSupplier(AdministrationId $administrationId, SupplierId $supplierId): ?PurchaseSupplierSnapshot;

    public function activeLineAccount(AdministrationId $administrationId, LedgerAccountId $id): ?LedgerAccount;

    public function activeLedgerAccount(AdministrationId $administrationId, LedgerAccountId $id): ?LedgerAccount;

    public function activeInputTaxCode(AdministrationId $administrationId, TaxCodeId $id): ?TaxCodeSelectionItem;

    public function activeTaxCode(AdministrationId $administrationId, TaxCodeId $id): ?TaxCodeSelectionItem;

    /** @return list<PurchaseSupplierSnapshot> */
    public function activeSuppliers(AdministrationId $administrationId): array;

    /** @return list<LedgerAccount> */
    public function activeLineAccounts(AdministrationId $administrationId): array;

    /** @return list<TaxCodeSelectionItem> */
    public function activeInputTaxCodes(AdministrationId $administrationId): array;
}
