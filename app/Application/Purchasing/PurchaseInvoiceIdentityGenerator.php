<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;

interface PurchaseInvoiceIdentityGenerator
{
    public function invoiceId(): PurchaseInvoiceId;

    public function lineId(): PurchaseInvoiceLineId;
}
