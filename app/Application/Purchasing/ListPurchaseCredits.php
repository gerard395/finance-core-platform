<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class ListPurchaseCredits
{
    public function __construct(private PurchaseCreditInvoiceRepository $credits) {}

    public function execute(AdministrationId $admin): array
    {
        return $this->credits->list($admin);
    }
}
