<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\RelationsRole;
use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\Entities\RolePermission;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\RolePermissionId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RolePermissionRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RoleRecord;
use LogicException;

final readonly class RelationsAuthorizationProvisioner
{
    public function __construct(
        private PermissionRepository $permissions,
        private RoleRepository $roles,
        private RolePermissionRepository $assignments,
        private TransactionManager $transactions,
    ) {}

    public function provision(): void
    {
        $this->transactions->run(function (): void {
            foreach (RelationsPermission::cases() as $definition) {
                $this->provisionPermission($definition);
            }

            foreach (RelationsRole::cases() as $definition) {
                $this->provisionRole($definition);

                foreach ($definition->permissions() as $permission) {
                    $this->provisionAssignment($definition, $permission);
                }
            }
        });
    }

    private function provisionPermission(RelationsPermission $definition): void
    {
        $byId = PermissionRecord::query()->find($definition->id()->toString());
        $byCode = PermissionRecord::query()->where('code', $definition->code()->toString())->first();
        $this->assertSameDefinition($byId?->getAttribute('id'), $byCode?->getAttribute('id'), 'Permission', $definition->value);

        $permission = $this->permissions->findById($definition->id()) ?? new Permission(
            $definition->id(),
            $definition->code(),
            $definition->name(),
            null,
            PermissionStatus::Active,
        );

        if (! $permission->code()->equals($definition->code())) {
            throw new LogicException("Permission identity conflicts with canonical code {$definition->value}.");
        }

        $permission->rename($definition->name());
        $permission->activate();
        $this->permissions->save($permission);
    }

    private function provisionRole(RelationsRole $definition): void
    {
        $byId = RoleRecord::query()->find($definition->id()->toString());
        $byCode = RoleRecord::query()->where('code', $definition->code()->toString())->first();
        $this->assertSameDefinition($byId?->getAttribute('id'), $byCode?->getAttribute('id'), 'Role', $definition->value);

        $role = $this->roles->findById($definition->id()) ?? new Role(
            $definition->id(),
            $definition->code(),
            $definition->name(),
            null,
            RoleStatus::Active,
        );

        if (! $role->code()->equals($definition->code())) {
            throw new LogicException("Role identity conflicts with canonical code {$definition->value}.");
        }

        $role->rename($definition->name());
        $role->activate();
        $this->roles->save($role);
    }

    private function provisionAssignment(RelationsRole $role, RelationsPermission $permission): void
    {
        $existing = RolePermissionRecord::query()
            ->where('role_id', $role->id()->toString())
            ->where('permission_id', $permission->id()->toString())
            ->first();

        if ($existing !== null) {
            $assignment = $this->assignments->findById(new RolePermissionId(new Uuid($existing->getAttribute('id'))));
            if ($assignment === null) {
                throw new LogicException('Existing Relations role-permission assignment could not be loaded.');
            }
            $assignment->activate();
            $this->assignments->save($assignment);

            return;
        }

        $id = $this->assignmentId($role, $permission);
        if (RolePermissionRecord::query()->find($id->toString()) !== null) {
            throw new LogicException('Stable Relations role-permission identity belongs to another assignment.');
        }

        $this->assignments->save(new RolePermission($id, $role->id(), $permission->id(), true));
    }

    private function assertSameDefinition(?string $idByIdentity, ?string $idByCode, string $type, string $code): void
    {
        if ($idByIdentity !== null && $idByCode !== null && $idByIdentity === $idByCode) {
            return;
        }

        if ($idByIdentity !== null || $idByCode !== null) {
            throw new LogicException("{$type} definition is ambiguous for canonical code {$code}.");
        }
    }

    private function assignmentId(RelationsRole $role, RelationsPermission $permission): RolePermissionId
    {
        $uuid = match ($role) {
            RelationsRole::Viewer => match ($permission) {
                RelationsPermission::View => '15a235b3-b68a-4808-8cf5-94c2ef1e5a30',
                default => throw new LogicException('Permission is not part of the Viewer role definition.'),
            },
            RelationsRole::Editor => match ($permission) {
                RelationsPermission::View => 'c92aadbc-9c0f-42cd-9739-4ef05068049d',
                RelationsPermission::Create => '8ed38f5a-b8ed-4bfd-a202-a221358bad33',
                RelationsPermission::Update => 'c9dd8314-d0e5-4310-9e1a-53e3598ac744',
                default => throw new LogicException('Permission is not part of the Editor role definition.'),
            },
            RelationsRole::Manager => match ($permission) {
                RelationsPermission::View => '5eb67bdd-3ccc-47d5-aa1a-466ad555beea',
                RelationsPermission::Create => '21b241a9-b6da-4de8-9e57-67faad814c4e',
                RelationsPermission::Update => 'ad749768-b919-41ae-9df3-bb76d670db52',
                RelationsPermission::ClassifyCustomer => '48060712-e6d8-4a6f-a29a-c4f00417fe5e',
                RelationsPermission::ClassifySupplier => '6191cd0c-10e2-4765-98ad-397adf0efe65',
            },
        };

        return new RolePermissionId(new Uuid($uuid));
    }
}
