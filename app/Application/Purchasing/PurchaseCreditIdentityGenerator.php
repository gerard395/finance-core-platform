<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;

interface PurchaseCreditIdentityGenerator
{
    public function creditId(): PurchaseCreditInvoiceId;

    public function lineId(): PurchaseCreditInvoiceLineId;
}
