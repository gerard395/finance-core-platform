<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Enums;

enum PurchaseSupplyClassification: string
{
    case Goods = 'goods';
    case GeneralRuleService = 'general_rule_service';
}
