<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;

enum RelationsRole: string
{
    case Viewer = 'RELATIONS_VIEWER';
    case Editor = 'RELATIONS_EDITOR';
    case Manager = 'RELATIONS_MANAGER';

    public function id(): RoleId
    {
        return new RoleId(new Uuid(match ($this) {
            self::Viewer => '56383942-fe7c-470e-89b8-7027684284ad',
            self::Editor => '2f563fad-c754-46a7-97e7-0433acb03dd8',
            self::Manager => '30b3565d-aa7b-444d-b625-9397c849c037',
        }));
    }

    public function code(): RoleCode
    {
        return new RoleCode($this->value);
    }

    public function name(): RoleName
    {
        return new RoleName(match ($this) {
            self::Viewer => 'Relations Viewer',
            self::Editor => 'Relations Editor',
            self::Manager => 'Relations Manager',
        });
    }

    /** @return list<RelationsPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Viewer => [RelationsPermission::View],
            self::Editor => [
                RelationsPermission::View,
                RelationsPermission::Create,
                RelationsPermission::Update,
            ],
            self::Manager => RelationsPermission::cases(),
        };
    }
}
