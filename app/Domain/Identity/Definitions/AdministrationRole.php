<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;

enum AdministrationRole: string
{
    case Manager = 'ADMINISTRATION_MANAGER';

    public function id(): RoleId
    {
        return new RoleId(new Uuid('c5e170dd-9272-4a11-bc67-3abe9eed7249'));
    }

    public function code(): RoleCode
    {
        return new RoleCode($this->value);
    }

    public function name(): RoleName
    {
        return new RoleName('Administration Manager');
    }

    /** @return list<AdministrationPermission> */
    public function permissions(): array
    {
        return [AdministrationPermission::UpdateSettings];
    }
}
