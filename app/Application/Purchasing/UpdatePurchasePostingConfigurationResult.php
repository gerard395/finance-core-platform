<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum UpdatePurchasePostingConfigurationResult
{
    case Saved;
    case InvalidReference;
}
