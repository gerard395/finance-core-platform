<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Identity\Definitions\BankingRole;
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

final readonly class BankingAuthorizationProvisioner
{
    public function __construct(private PermissionRepository $permissions, private RoleRepository $roles, private RolePermissionRepository $assignments, private TransactionManager $transactions) {}

    public function provision(): void
    {
        $this->transactions->run(function (): void {
            foreach (BankingPermission::cases() as $permission) {
                $this->definition($permission->id()->toString(), $permission->value, PermissionRecord::class, 'Permission');
                $entity = $this->permissions->findById($permission->id()) ?? new Permission($permission->id(), $permission->code(), $permission->name(), null, PermissionStatus::Active);
                if (! $entity->code()->equals($permission->code())) {
                    throw new LogicException("Permission identity conflicts with canonical code {$permission->value}.");
                }
                $entity->rename($permission->name());
                $entity->activate();
                $this->permissions->save($entity);
            }
            foreach (BankingRole::cases() as $role) {
                $this->definition($role->id()->toString(), $role->value, RoleRecord::class, 'Role');
                $entity = $this->roles->findById($role->id()) ?? new Role($role->id(), $role->code(), $role->name(), null, RoleStatus::Active);
                if (! $entity->code()->equals($role->code())) {
                    throw new LogicException("Role identity conflicts with canonical code {$role->value}.");
                }
                $entity->rename($role->name());
                $entity->activate();
                $this->roles->save($entity);
                foreach ($role->permissions() as $permission) {
                    $this->assignment($role, $permission);
                }
            }
        });
    }

    private function definition(string $id, string $code, string $model, string $type): void
    {
        $byId = $model::query()->find($id);
        $byCode = $model::query()->where('code', $code)->first();
        if (($byId !== null || $byCode !== null) && $byId?->getAttribute('id') !== $byCode?->getAttribute('id')) {
            throw new LogicException("{$type} definition is ambiguous for canonical code {$code}.");
        }
    }

    private function assignment(BankingRole $role, BankingPermission $permission): void
    {
        $existing = RolePermissionRecord::query()->where('role_id', $role->id()->toString())->where('permission_id', $permission->id()->toString())->first();
        if ($existing !== null) {
            $entity = $this->assignments->findById(new RolePermissionId(new Uuid($existing->getAttribute('id'))));
            if ($entity === null) {
                throw new LogicException('Existing Banking role-permission assignment could not be loaded.');
            }
            $entity->activate();
            $this->assignments->save($entity);

            return;
        }
        $id = new RolePermissionId(new Uuid(match ($role) {
            BankingRole::Manager => $permission === BankingPermission::View ? 'b2030000-0000-4000-8000-000000000001' : 'b2030000-0000-4000-8000-000000000002',
            BankingRole::Poster => $permission === BankingPermission::View ? 'b2030000-0000-4000-8000-000000000003' : 'b2030000-0000-4000-8000-000000000004',
            BankingRole::ReversalOperator => $permission === BankingPermission::View ? 'b2030000-0000-4000-8000-000000000005' : 'b2030000-0000-4000-8000-000000000006',
        }));
        if (RolePermissionRecord::query()->find($id->toString()) !== null) {
            throw new LogicException('Stable Banking role-permission identity belongs to another assignment.');
        }
        $this->assignments->save(new RolePermission($id, $role->id(), $permission->id(), true));
    }
}
