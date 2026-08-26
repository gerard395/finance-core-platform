<?php

declare(strict_types=1);

namespace App\Application\Banking;

interface BankingPostingConfigurationStore
{
    public function save(BankingPostingConfiguration $configuration): bool;
}
