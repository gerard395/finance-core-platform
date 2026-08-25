<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Definitions\DeliveryOperationsPermission;
use App\Domain\Identity\Definitions\DeliveryOperationsRole;
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

final readonly class DeliveryOperationsAuthorizationProvisioner
{
    private const string ASSIGNMENT_ID = '8e510530-4723-46da-932c-dac030c13ac5';

    public function __construct(
        private PermissionRepository $permissions,
        private RoleRepository $roles,
        private RolePermissionRepository $assignments,
        private TransactionManager $transactions,
    ) {}

    public function provision(): void
    {
        $this->transactions->run(function (): void {
            $permissionDefinition = DeliveryOperationsPermission::ResolveUnknownOutcome;
            $roleDefinition = DeliveryOperationsRole::Operator;

            $permissionById = PermissionRecord::query()->find($permissionDefinition->id()->toString());
            $permissionByCode = PermissionRecord::query()->where('code', $permissionDefinition->value)->first();
            $this->assertSameDefinition($permissionById?->getAttribute('id'), $permissionByCode?->getAttribute('id'), 'Permission');
            $permission = $this->permissions->findById($permissionDefinition->id()) ?? new Permission(
                $permissionDefinition->id(), $permissionDefinition->code(), $permissionDefinition->name(),
                'Resolve an ambiguous document delivery outcome through an explicit audited operation.', PermissionStatus::Active,
            );
            if (! $permission->code()->equals($permissionDefinition->code())) {
                throw new LogicException('Delivery operations permission identity conflicts with its canonical code.');
            }
            $permission->rename($permissionDefinition->name());
            $permission->activate();
            $this->permissions->save($permission);

            $roleById = RoleRecord::query()->find($roleDefinition->id()->toString());
            $roleByCode = RoleRecord::query()->where('code', $roleDefinition->value)->first();
            $this->assertSameDefinition($roleById?->getAttribute('id'), $roleByCode?->getAttribute('id'), 'Role');
            $role = $this->roles->findById($roleDefinition->id()) ?? new Role(
                $roleDefinition->id(), $roleDefinition->code(), $roleDefinition->name(),
                'Operate ambiguous document delivery outcomes within an assigned Administration.', RoleStatus::Active,
            );
            if (! $role->code()->equals($roleDefinition->code())) {
                throw new LogicException('Delivery operations role identity conflicts with its canonical code.');
            }
            $role->rename($roleDefinition->name());
            $role->activate();
            $this->roles->save($role);

            $existing = RolePermissionRecord::query()
                ->where('role_id', $roleDefinition->id()->toString())
                ->where('permission_id', $permissionDefinition->id()->toString())->first();
            if ($existing !== null) {
                $assignment = $this->assignments->findById(new RolePermissionId(new Uuid($existing->getAttribute('id'))));
                if ($assignment === null) {
                    throw new LogicException('Existing Delivery operations role-permission assignment could not be loaded.');
                }
                $assignment->activate();
                $this->assignments->save($assignment);

                return;
            }

            $assignmentId = new RolePermissionId(new Uuid(self::ASSIGNMENT_ID));
            if (RolePermissionRecord::query()->find($assignmentId->toString()) !== null) {
                throw new LogicException('Stable Delivery operations role-permission identity belongs to another assignment.');
            }
            $this->assignments->save(new RolePermission($assignmentId, $roleDefinition->id(), $permissionDefinition->id(), true));
        });
    }

    private function assertSameDefinition(?string $idByIdentity, ?string $idByCode, string $type): void
    {
        if ($idByIdentity !== null && $idByCode !== null && $idByIdentity === $idByCode) {
            return;
        }
        if ($idByIdentity !== null || $idByCode !== null) {
            throw new LogicException("{$type} definition is ambiguous for the canonical Delivery operations definition.");
        }
    }
}
