<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;

final readonly class GetPurchaseInvoiceMasterData
{
    public function __construct(private PurchaseInvoiceMasterDataReader $reader, private TaxTreatmentDefinitionRepository $treatments) {}

    public function execute(AdministrationId $admin): array
    {
        $taxCodes = $this->reader->activePurchaseTaxCodes($admin);

        return [
            'suppliers' => $this->reader->activeSuppliers($admin),
            'accounts' => $this->reader->activeLineAccounts($admin),
            'tax_codes' => $taxCodes,
            'international_tax_code_ids' => array_map(static fn ($id): string => $id->toString(), $this->reader->internationalTaxCodeIds($admin)),
            'user_specified_deductibility_tax_code_ids' => array_values(array_map(
                static fn ($taxCode): string => $taxCode->id()->toString(),
                array_filter($taxCodes, fn ($taxCode): bool => $this->treatments->resolveActiveForTaxCode($admin, $taxCode->id())->definition?->deductibilityPolicy() === DeductibilityPolicy::UserSpecifiedLineRate),
            )),
        ];
    }
}
