<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface SalesPostingConfigurationStore
{
    public function save(SalesPostingConfiguration $configuration): SalesPostingConfigurationWriteResult;
}
