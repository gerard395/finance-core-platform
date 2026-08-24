<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum IcpClassification: string
{
    case None = 'none';
    case Service = 'service';
    case GoodsSupply = 'goods_supply';
}
