<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class ListPurchaseInvoices
{
    public function __construct(private PurchaseInvoiceRepository $repository) {}

    /** @return list<PurchaseInvoiceListItem> */
    public function execute(AdministrationId $admin): array
    {
        return array_map(PurchaseInvoiceListItem::from(...), $this->repository->list($admin));
    }
}
