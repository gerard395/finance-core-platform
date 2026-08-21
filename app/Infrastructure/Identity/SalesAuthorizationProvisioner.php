<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\Definitions\SalesRole;
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

final readonly class SalesAuthorizationProvisioner
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
            foreach (SalesPermission::cases() as $definition) {
                $this->provisionPermission($definition);
            }

            foreach (SalesRole::cases() as $definition) {
                $this->provisionRole($definition);

                foreach ($definition->permissions() as $permission) {
                    $this->provisionAssignment($definition, $permission);
                }
            }
        });
    }

    private function provisionPermission(SalesPermission $definition): void
    {
        $byId = PermissionRecord::query()->find($definition->id()->toString());
        $byCode = PermissionRecord::query()->where('code', $definition->code()->toString())->first();
        $this->assertSameDefinition($byId?->getAttribute('id'), $byCode?->getAttribute('id'), 'Permission', $definition->value);

        $permission = $this->permissions->findById($definition->id()) ?? new Permission(
            $definition->id(), $definition->code(), $definition->name(), null, PermissionStatus::Active,
        );

        if (! $permission->code()->equals($definition->code())) {
            throw new LogicException("Permission identity conflicts with canonical code {$definition->value}.");
        }

        $permission->rename($definition->name());
        $permission->activate();
        $this->permissions->save($permission);
    }

    private function provisionRole(SalesRole $definition): void
    {
        $byId = RoleRecord::query()->find($definition->id()->toString());
        $byCode = RoleRecord::query()->where('code', $definition->code()->toString())->first();
        $this->assertSameDefinition($byId?->getAttribute('id'), $byCode?->getAttribute('id'), 'Role', $definition->value);

        $role = $this->roles->findById($definition->id()) ?? new Role(
            $definition->id(), $definition->code(), $definition->name(), null, RoleStatus::Active,
        );

        if (! $role->code()->equals($definition->code())) {
            throw new LogicException("Role identity conflicts with canonical code {$definition->value}.");
        }

        $role->rename($definition->name());
        $role->activate();
        $this->roles->save($role);
    }

    private function provisionAssignment(SalesRole $role, SalesPermission $permission): void
    {
        $existing = RolePermissionRecord::query()
            ->where('role_id', $role->id()->toString())
            ->where('permission_id', $permission->id()->toString())
            ->first();

        if ($existing !== null) {
            $assignment = $this->assignments->findById(new RolePermissionId(new Uuid($existing->getAttribute('id'))));
            if ($assignment === null) {
                throw new LogicException('Existing Sales role-permission assignment could not be loaded.');
            }
            $assignment->activate();
            $this->assignments->save($assignment);

            return;
        }

        $id = $this->assignmentId($role, $permission);
        if (RolePermissionRecord::query()->find($id->toString()) !== null) {
            throw new LogicException('Stable Sales role-permission identity belongs to another assignment.');
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

    private function assignmentId(SalesRole $role, SalesPermission $permission): RolePermissionId
    {
        $uuid = match ($role) {
            SalesRole::Viewer => match ($permission) {
                SalesPermission::View => '0484f5aa-7171-4962-b05a-43e8de4a2156',
                default => throw new LogicException('Permission is not part of the Sales Viewer role definition.'),
            },
            SalesRole::Editor => match ($permission) {
                SalesPermission::View => 'c252c7ce-53ad-4ccb-94d6-35f8db516414',
                SalesPermission::ManageQuotations => '1349ea61-e390-4ca5-a154-cb1275b3d54c',
                SalesPermission::ManageOrders => '309c879f-c983-4bd4-9cce-25144b55d7b4',
                SalesPermission::ManageInvoiceDrafts => 'c6e4d6be-8bff-486e-a6a2-b0fb00f5507f',
                SalesPermission::ManageCreditInvoiceDrafts => '56a118c7-2897-4132-9f5f-dac83608b335',
                default => throw new LogicException('Permission is not part of the Sales Editor role definition.'),
            },
            SalesRole::Manager => match ($permission) {
                SalesPermission::View => 'dd422d87-3ee3-40ed-aa0c-0ce747b0a702',
                SalesPermission::ManageQuotations => 'fd80811e-4c83-4da0-8852-efb8d4910e3d',
                SalesPermission::ManageOrders => '77e03638-3a2c-4731-b2cc-cff821094c3f',
                SalesPermission::ManageInvoiceDrafts => '9aac5a2a-3281-49ca-abb5-df2c1f34ee4f',
                SalesPermission::ManageCreditInvoiceDrafts => '1fab4f72-b2d7-4572-9143-0469d5dcee93',
                SalesPermission::IssueInvoices => 'a926ddca-2956-4b32-a1dd-1cc2a8d2c0cb',
                SalesPermission::IssueCreditInvoices => 'c23cd903-61fd-4990-b80a-23b85a2830ba',
                default => throw new LogicException('Permission is not part of the Sales Manager role definition.'),
            },
            SalesRole::Poster => match ($permission) {
                SalesPermission::PostInvoices => '60535af3-98c1-4f88-8b52-166651c7b37f',
                SalesPermission::PostCreditInvoices => '94536832-367b-4be3-8b61-b133bd409ff3',
                default => throw new LogicException('Permission is not part of the Sales Poster role definition.'),
            },
        };

        return new RolePermissionId(new Uuid($uuid));
    }
}
