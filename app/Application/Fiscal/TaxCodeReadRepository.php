<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;

interface TaxCodeReadRepository
{
    /** @return list<TaxCodeSelectionItem> */
    public function findActiveForAdministrationAndDirection(
        AdministrationId $administrationId,
        TaxPostingDirection $direction,
    ): array;

    public function findByIdForAdministration(
        AdministrationId $administrationId,
        TaxCodeId $taxCodeId,
    ): ?TaxCodeSelectionItem;
}
