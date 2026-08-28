<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseCreditClaimReader;
use App\Domain\Administration\ValueObjects\AdministrationId;
use Illuminate\Support\Facades\DB;

final class EloquentPurchaseCreditClaimReader implements PurchaseCreditClaimReader
{
    public function claimed(AdministrationId $admin, array $lineIds): array
    {
        $ids = array_map(static fn ($id): string => $id->toString(), $lineIds);
        $claimed = array_fill_keys($ids, false);
        foreach (DB::table('purchase_credit_source_line_claims')->where('administration_id', $admin->toString())->whereIn('source_purchase_invoice_line_id', $ids)->pluck('source_purchase_invoice_line_id') as $id) {
            $claimed[$id] = true;
        }

        return $claimed;
    }
}
