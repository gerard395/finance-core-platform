<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Purchasing\PurchaseCreditIdentityGenerator;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelPurchaseCreditIdentityGenerator implements PurchaseCreditIdentityGenerator
{
    public function creditId(): PurchaseCreditInvoiceId
    {
        return new PurchaseCreditInvoiceId(new Uuid((string) Str::uuid()));
    }

    public function lineId(): PurchaseCreditInvoiceLineId
    {
        return new PurchaseCreditInvoiceLineId(new Uuid((string) Str::uuid()));
    }
}
