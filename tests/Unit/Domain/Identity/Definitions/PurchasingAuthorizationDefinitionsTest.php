<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Definitions;

use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Identity\Definitions\PurchasingRole;
use PHPUnit\Framework\TestCase;

final class PurchasingAuthorizationDefinitionsTest extends TestCase
{
    public function test_permissions_and_roles_have_exact_stable_independent_contracts(): void
    {
        self::assertSame([
            'PURCHASING.VIEW', 'PURCHASING.INVOICES_DRAFT_MANAGE', 'PURCHASING.INVOICES_FINALIZE', 'PURCHASING.INVOICES_POST',
        ], array_map(static fn (PurchasingPermission $permission): string => $permission->value, PurchasingPermission::cases()));
        self::assertCount(4, array_unique(array_map(static fn (PurchasingPermission $permission): string => $permission->id()->toString(), PurchasingPermission::cases())));
        self::assertSame(['PURCHASING_MANAGER', 'PURCHASING_POSTER'], array_map(static fn (PurchasingRole $role): string => $role->value, PurchasingRole::cases()));
        self::assertSame([
            'PURCHASING.VIEW', 'PURCHASING.INVOICES_DRAFT_MANAGE', 'PURCHASING.INVOICES_FINALIZE',
        ], array_map(static fn (PurchasingPermission $permission): string => $permission->value, PurchasingRole::Manager->permissions()));
        self::assertSame(['PURCHASING.VIEW', 'PURCHASING.INVOICES_POST'], array_map(static fn (PurchasingPermission $permission): string => $permission->value, PurchasingRole::Poster->permissions()));
    }
}
