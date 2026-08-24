<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesPostingConfigurationReadStatus
{
    case Success;
    case Missing;
    case InvalidReference;
}
