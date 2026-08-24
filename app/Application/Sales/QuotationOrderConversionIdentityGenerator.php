<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;

interface QuotationOrderConversionIdentityGenerator
{
    public function orderId(): OrderId;

    public function orderLineId(): OrderLineId;
}
