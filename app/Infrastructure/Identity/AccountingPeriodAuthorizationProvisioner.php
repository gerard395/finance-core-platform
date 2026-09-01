<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Definitions\AccountingPeriodPermission;
use App\Domain\Identity\Definitions\AccountingPeriodRole;
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

final readonly class AccountingPeriodAuthorizationProvisioner
{
    public function __construct(private PermissionRepository $permissions, private RoleRepository $roles, private RolePermissionRepository $assignments, private TransactionManager $transactions) {}

    public function provision(): void
    {
        $this->transactions->run(function () {
            foreach (AccountingPeriodPermission::cases() as $p) {
                $this->definition($p->id()->toString(), $p->value, PermissionRecord::class);
                $e = $this->permissions->findById($p->id()) ?? new Permission($p->id(), $p->code(), $p->name(), null, PermissionStatus::Active);
                if (! $e->code()->equals($p->code())) {
                    throw new LogicException('Permission collision.');
                }$e->rename($p->name());
                $e->activate();
                $this->permissions->save($e);
            }foreach (AccountingPeriodRole::cases() as $r) {
                $this->definition($r->id()->toString(), $r->value, RoleRecord::class);
                $e = $this->roles->findById($r->id()) ?? new Role($r->id(), $r->code(), $r->name(), null, RoleStatus::Active);
                if (! $e->code()->equals($r->code())) {
                    throw new LogicException('Role collision.');
                }$e->rename($r->name());
                $e->activate();
                $this->roles->save($e);
                foreach ($r->permissions() as $p) {
                    $this->assignment($r, $p);
                }
            }
        });
    }

    private function definition(string $id, string $code, string $model): void
    {
        $a = $model::query()->find($id);
        $b = $model::query()->where('code', $code)->first();
        if (($a || $b) && $a?->id !== $b?->id) {
            throw new LogicException('Canonical definition collision.');
        }
    }

    private function assignment(AccountingPeriodRole $r, AccountingPeriodPermission $p): void
    {
        $x = RolePermissionRecord::query()->where('role_id', $r->id()->toString())->where('permission_id', $p->id()->toString())->first();
        if ($x) {
            $e = $this->assignments->findById(new RolePermissionId(new Uuid($x->id)));
            if (! $e) {
                throw new LogicException('Assignment missing.');
            }$e->activate();
            $this->assignments->save($e);

            return;
        }$n = match ($r) {
            AccountingPeriodRole::Manager => match ($p) {
                AccountingPeriodPermission::View => 1,AccountingPeriodPermission::Manage => 2,AccountingPeriodPermission::Close => 3,default => throw new LogicException
            },AccountingPeriodRole::Reopener => match ($p) {
                AccountingPeriodPermission::View => 4,AccountingPeriodPermission::Reopen => 5,default => throw new LogicException
            }
        };
        $id = new RolePermissionId(new Uuid(sprintf('a9030000-0000-4000-8000-%012d', $n)));
        if (RolePermissionRecord::query()->find($id->toString())) {
            throw new LogicException('Assignment collision.');
        }$this->assignments->save(new RolePermission($id, $r->id(), $p->id(), true));
    }
}
