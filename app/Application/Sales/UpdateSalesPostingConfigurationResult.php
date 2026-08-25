<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum UpdateSalesPostingConfigurationResult
{
    case Saved;
    case InvalidReference;
}
