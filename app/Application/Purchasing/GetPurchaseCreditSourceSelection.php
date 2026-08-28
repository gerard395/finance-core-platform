<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

final readonly class GetPurchaseCreditSourceSelection
{
    public function __construct(private PurchaseCreditSourceReader $sources, private PurchaseCreditClaimReader $claims) {}

    public function execute(AdministrationId $admin, PurchaseInvoiceId $id): ?PurchaseCreditSourceSelection
    {
        $source = $this->sources->read($admin, $id);
        if ($source === null || $source->invoice->status() !== PurchaseInvoiceStatus::Posted || $source->invoice->currency()->code() !== 'EUR') {
            return null;
        }

        return new PurchaseCreditSourceSelection($source, $this->claims->claimed($admin, array_map(static fn ($line) => $line->id(), $source->invoice->lines())));
    }
}
