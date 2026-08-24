<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesCreditInvoiceIdentityGenerator;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelSalesCreditInvoiceIdentityGenerator implements SalesCreditInvoiceIdentityGenerator
{
    public function creditInvoiceId(): SalesCreditInvoiceId
    {
        return new SalesCreditInvoiceId(new Uuid(Str::uuid()->toString()));
    }
}
