<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;

final readonly class GetPurchaseCreditInvoice
{
    public function __construct(private PurchaseCreditInvoiceRepository $credits) {}

    public function execute(AdministrationId $admin, PurchaseCreditInvoiceId $id): ?PurchaseCreditInvoice
    {
        return $this->credits->find($admin, $id);
    }
}
