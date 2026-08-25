<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum PurchasePostingConfigurationReadStatus
{
    case Missing;
    case Success;
    case InvalidReference;
}
