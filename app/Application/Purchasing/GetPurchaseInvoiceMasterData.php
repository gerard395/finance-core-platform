<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Application\Fiscal\TaxTreatmentDefinitionSelectionStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;

final readonly class GetPurchaseInvoiceMasterData
{
    public function __construct(private PurchaseInvoiceMasterDataReader $reader, private TaxTreatmentDefinitionRepository $treatments) {}

    public function execute(AdministrationId $admin): array
    {
        $taxCodes = $this->reader->activePurchaseTaxCodes($admin);
        $domesticIds = array_fill_keys(array_map(static fn ($taxCode): string => $taxCode->id()->toString(), $this->reader->activeInputTaxCodes($admin)), true);
        $linkedIds = array_fill_keys(array_map(static fn ($id): string => $id->toString(), $this->reader->internationalTaxCodeIds($admin)), true);
        $international = [];
        foreach ($taxCodes as $taxCode) {
            $selection = $this->treatments->resolveActiveForTaxCode($admin, $taxCode->id());
            if ($selection->status === TaxTreatmentDefinitionSelectionStatus::Found) {
                $international[$taxCode->id()->toString()] = $selection->definition;
            }
        }
        $taxCodes = array_values(array_filter($taxCodes, static fn ($taxCode): bool => isset($international[$taxCode->id()->toString()]) || (isset($domesticIds[$taxCode->id()->toString()]) && ! isset($linkedIds[$taxCode->id()->toString()]))));

        return [
            'suppliers' => $this->reader->activeSuppliers($admin),
            'accounts' => $this->reader->activeLineAccounts($admin),
            'tax_codes' => $taxCodes,
            'international_tax_code_ids' => array_keys($international),
            'user_specified_deductibility_tax_code_ids' => array_values(array_map(
                static fn ($taxCode): string => $taxCode->id()->toString(),
                array_filter($taxCodes, static fn ($taxCode): bool => ($international[$taxCode->id()->toString()] ?? null)?->deductibilityPolicy() === DeductibilityPolicy::UserSpecifiedLineRate),
            )),
        ];
    }
}
