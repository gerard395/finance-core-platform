<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;

enum AdministrationPermission: string
{
    case UpdateSettings = 'ADMINISTRATION.SETTINGS_UPDATE';

    public function id(): PermissionId
    {
        return new PermissionId(new Uuid('53b52b3e-38b4-4e0a-a98a-6dd93e678a83'));
    }

    public function code(): PermissionCode
    {
        return new PermissionCode($this->value);
    }

    public function name(): PermissionName
    {
        return new PermissionName('Change Administration Settings');
    }
}
