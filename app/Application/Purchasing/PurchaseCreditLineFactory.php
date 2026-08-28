<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\Entities\PurchaseCreditInvoiceLine;

final readonly class PurchaseCreditLineFactory
{
    public function __construct(private PurchaseCreditIdentityGenerator $ids) {}

    /** @return list<PurchaseCreditInvoiceLine>|null */
    public function selected(PurchaseCreditSource $source, array $ids): ?array
    {
        if ($ids === []) {
            return [];
        } $wanted = [];
        foreach ($ids as $id) {
            $key = $id->toString();
            if (isset($wanted[$key])) {
                return null;
            }$wanted[$key] = true;
        }
        $lines = [];
        foreach ($source->invoice->lines() as $line) {
            $key = $line->id()->toString();
            if (! isset($wanted[$key])) {
                continue;
            }$lines[] = new PurchaseCreditInvoiceLine($this->ids->lineId(), $line->description(), $line->quantity(), $line->unitPrice(), $line->id(), $line->account(), $line->tax(), $line->net(), $line->taxAmount(), $line->gross(), $source->taxPostingIds[$key] ?? null);
            unset($wanted[$key]);
        }

        return $wanted === [] ? $lines : null;
    }
}
