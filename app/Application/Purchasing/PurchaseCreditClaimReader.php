<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;

interface PurchaseCreditClaimReader
{
    /** @param list<PurchaseInvoiceLineId> $lineIds @return array<string, bool> */
    public function claimed(AdministrationId $admin, array $lineIds): array;
}
