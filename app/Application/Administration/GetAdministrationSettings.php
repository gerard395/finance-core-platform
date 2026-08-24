<?php

declare(strict_types=1);

namespace App\Application\Administration;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class GetAdministrationSettings
{
    public function __construct(private AdministrationSettingsReader $settings) {}

    public function execute(AdministrationId $administrationId): ?AdministrationSettings
    {
        return $this->settings->findSettings($administrationId);
    }
}
