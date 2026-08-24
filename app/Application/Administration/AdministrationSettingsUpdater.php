<?php

declare(strict_types=1);

namespace App\Application\Administration;

use App\Domain\Administration\Entities\Administration;

interface AdministrationSettingsUpdater
{
    public function updateSettings(Administration $administration): bool;
}
