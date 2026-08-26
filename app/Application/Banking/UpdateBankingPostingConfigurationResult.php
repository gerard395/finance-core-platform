<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum UpdateBankingPostingConfigurationResult
{
    case Saved;
    case InvalidReference;
}
