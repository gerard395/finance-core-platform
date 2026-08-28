<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;

final readonly class ListEligiblePurchaseCreditSources
{
    public function __construct(private PurchaseInvoiceRepository $invoices) {}

    public function execute(AdministrationId $admin): array
    {
        return array_values(array_filter($this->invoices->list($admin), static fn ($invoice): bool => $invoice->status() === PurchaseInvoiceStatus::Posted && $invoice->currency()->code() === 'EUR'));
    }
}
