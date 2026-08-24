<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Fiscal\TaxCodeReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;

final readonly class SalesTaxCodeResolver
{
    public function __construct(private TaxCodeReadRepository $taxCodes) {}

    public function resolve(AdministrationId $administrationId, TaxCodeId $taxCodeId): SalesTaxCodeResolution
    {
        $item = $this->taxCodes->findByIdForAdministration($administrationId, $taxCodeId);

        if ($item === null) {
            return SalesTaxCodeResolution::failure(SalesTaxCodeResolutionStatus::NotFound);
        }
        if ($item->direction() !== TaxPostingDirection::Output) {
            return SalesTaxCodeResolution::failure(SalesTaxCodeResolutionStatus::WrongDirection);
        }
        if ($item->status() !== TaxCodeStatus::Active) {
            return SalesTaxCodeResolution::failure(SalesTaxCodeResolutionStatus::Inactive);
        }

        return SalesTaxCodeResolution::success($item->toTaxCode());
    }
}
