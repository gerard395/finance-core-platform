<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Identity\Definitions\PurchasingRole;
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

final readonly class PurchasingAuthorizationProvisioner
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
            foreach (PurchasingPermission::cases() as $permission) {
                $this->provisionPermission($permission);
            }
            foreach (PurchasingRole::cases() as $role) {
                $this->provisionRole($role);
                foreach ($role->permissions() as $permission) {
                    $this->provisionAssignment($role, $permission);
                }
            }
        });
    }

    private function provisionPermission(PurchasingPermission $definition): void
    {
        $byId = PermissionRecord::query()->find($definition->id()->toString());
        $byCode = PermissionRecord::query()->where('code', $definition->code()->toString())->first();
        $this->assertSameDefinition($byId?->getAttribute('id'), $byCode?->getAttribute('id'), 'Permission', $definition->value);
        $permission = $this->permissions->findById($definition->id()) ?? new Permission($definition->id(), $definition->code(), $definition->name(), null, PermissionStatus::Active);
        if (! $permission->code()->equals($definition->code())) {
            throw new LogicException("Permission identity conflicts with canonical code {$definition->value}.");
        }
        $permission->rename($definition->name());
        $permission->activate();
        $this->permissions->save($permission);
    }

    private function provisionRole(PurchasingRole $definition): void
    {
        $byId = RoleRecord::query()->find($definition->id()->toString());
        $byCode = RoleRecord::query()->where('code', $definition->code()->toString())->first();
        $this->assertSameDefinition($byId?->getAttribute('id'), $byCode?->getAttribute('id'), 'Role', $definition->value);
        $role = $this->roles->findById($definition->id()) ?? new Role($definition->id(), $definition->code(), $definition->name(), null, RoleStatus::Active);
        if (! $role->code()->equals($definition->code())) {
            throw new LogicException("Role identity conflicts with canonical code {$definition->value}.");
        }
        $role->rename($definition->name());
        $role->activate();
        $this->roles->save($role);
    }

    private function provisionAssignment(PurchasingRole $role, PurchasingPermission $permission): void
    {
        $existing = RolePermissionRecord::query()->where('role_id', $role->id()->toString())->where('permission_id', $permission->id()->toString())->first();
        if ($existing !== null) {
            $assignment = $this->assignments->findById(new RolePermissionId(new Uuid($existing->getAttribute('id'))));
            if ($assignment === null) {
                throw new LogicException('Existing Purchasing role-permission assignment could not be loaded.');
            }
            $assignment->activate();
            $this->assignments->save($assignment);

            return;
        }
        $id = $this->assignmentId($role, $permission);
        if (RolePermissionRecord::query()->find($id->toString()) !== null) {
            throw new LogicException('Stable Purchasing role-permission identity belongs to another assignment.');
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

    private function assignmentId(PurchasingRole $role, PurchasingPermission $permission): RolePermissionId
    {
        return new RolePermissionId(new Uuid(match ($role) {
            PurchasingRole::Manager => match ($permission) {
                PurchasingPermission::View => 'fd6dfc80-cd97-43b8-bc42-49d582e8fa41',
                PurchasingPermission::ManageInvoiceDrafts => 'e1288f44-c98c-4e88-a037-f5e2f019460d',
                PurchasingPermission::FinalizeInvoices => 'd0645632-177b-430f-8b6a-e52b8fc3fa13',
                default => throw new LogicException('Permission is not part of the Purchasing Manager role definition.'),
            },
            PurchasingRole::Poster => match ($permission) {
                PurchasingPermission::View => '73a69871-9083-4a35-ad89-0ba8d52911d4',
                PurchasingPermission::PostInvoices => '17c22b07-b0b2-46b5-b187-50a61201a48f',
                default => throw new LogicException('Permission is not part of the Purchasing Poster role definition.'),
            },
        }));
    }
}
