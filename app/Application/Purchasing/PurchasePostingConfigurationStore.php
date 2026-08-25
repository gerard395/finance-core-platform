<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

interface PurchasePostingConfigurationStore
{
    public function save(PurchasePostingConfiguration $configuration): bool;
}
