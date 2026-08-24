<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\QuotationOrderConversionIdentityGenerator;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelQuotationOrderConversionIdentityGenerator implements QuotationOrderConversionIdentityGenerator
{
    public function orderId(): OrderId
    {
        return new OrderId(new Uuid(Str::uuid()->toString()));
    }

    public function orderLineId(): OrderLineId
    {
        return new OrderLineId(new Uuid(Str::uuid()->toString()));
    }
}
