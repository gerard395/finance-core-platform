<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseInvoiceIdentityGenerator;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Shared\Identity\Uuid;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class LaravelPurchaseInvoiceIdentityGenerator implements PurchaseInvoiceIdentityGenerator
{
    public function invoiceId(): PurchaseInvoiceId
    {
        return new PurchaseInvoiceId(new Uuid(RamseyUuid::uuid4()->toString()));
    }

    public function lineId(): PurchaseInvoiceLineId
    {
        return new PurchaseInvoiceLineId(new Uuid(RamseyUuid::uuid4()->toString()));
    }
}
