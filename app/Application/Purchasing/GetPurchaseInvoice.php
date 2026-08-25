<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

final readonly class GetPurchaseInvoice
{
    public function __construct(private PurchaseInvoiceRepository $repository) {}

    public function execute(AdministrationId $admin, PurchaseInvoiceId $id): ?PurchaseInvoice
    {
        return $this->repository->find($admin, $id);
    }
}
