<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetPurchaseInvoiceMasterData
{
    public function __construct(private PurchaseInvoiceMasterDataReader $reader) {}

    public function execute(AdministrationId $admin): array
    {
        return ['suppliers' => $this->reader->activeSuppliers($admin), 'accounts' => $this->reader->activeLineAccounts($admin), 'tax_codes' => $this->reader->activePurchaseTaxCodes($admin), 'international_tax_code_ids' => array_map(static fn ($id): string => $id->toString(), $this->reader->internationalTaxCodeIds($admin))];
    }
}
