<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesPostingConfigurationWriteResult
{
    case Saved;
    case InvalidReference;
}
