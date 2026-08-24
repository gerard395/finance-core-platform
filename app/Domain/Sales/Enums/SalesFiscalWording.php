<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum SalesFiscalWording: string
{
    case None = 'none';
    case VatReverseCharged = 'vat_reverse_charged';
    case IntraCommunityGoodsSupply = 'intra_community_goods_supply';
}
