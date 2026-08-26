<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankingPostingConfigurationReadStatus
{
    case Missing;
    case Success;
    case InvalidReference;
}
