<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;

interface OpenItemSettlementStore
{
    public function appendSettlement(OpenItem $openItem, OpenItemSettlement $settlement, ?PaymentAllocationId $paymentAllocationId = null): void;
}
