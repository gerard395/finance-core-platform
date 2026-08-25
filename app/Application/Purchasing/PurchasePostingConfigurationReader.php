<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;

interface PurchasePostingConfigurationReader
{
    public function read(AdministrationId $administrationId): PurchasePostingConfigurationReadResult;
}
