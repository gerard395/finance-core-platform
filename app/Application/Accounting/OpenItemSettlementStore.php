<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemSettlement;

interface OpenItemSettlementStore
{
    public function appendSettlement(OpenItem $openItem, OpenItemSettlement $settlement): void;
}
