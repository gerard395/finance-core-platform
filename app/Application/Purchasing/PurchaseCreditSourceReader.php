<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

interface PurchaseCreditSourceReader
{
    public function read(AdministrationId $admin, PurchaseInvoiceId $invoiceId, bool $lock = false): ?PurchaseCreditSource;
}
