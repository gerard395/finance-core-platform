<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Definitions;

use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\Definitions\SalesRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesAuthorizationDefinitionsTest extends TestCase
{
    public function test_permissions_have_exact_canonical_codes_names_and_unique_stable_identities(): void
    {
        $definitions = SalesPermission::cases();
        $actual = [];
        foreach ($definitions as $permission) {
            $actual[$permission->code()->toString()] = $permission->name()->toString();
        }

        self::assertSame([
            'SALES.VIEW' => 'View Sales Documents',
            'SALES.QUOTATIONS_MANAGE' => 'Manage Quotations',
            'SALES.ORDERS_MANAGE' => 'Manage Sales Orders',
            'SALES.INVOICES_DRAFT_MANAGE' => 'Manage Sales Invoice Drafts',
            'SALES.INVOICES_ISSUE' => 'Issue Sales Invoices',
            'SALES.INVOICES_POST' => 'Post Sales Invoices',
            'SALES.CREDIT_INVOICES_DRAFT_MANAGE' => 'Manage Sales Credit Invoice Drafts',
            'SALES.CREDIT_INVOICES_ISSUE' => 'Issue Sales Credit Invoices',
            'SALES.CREDIT_INVOICES_POST' => 'Post Sales Credit Invoices',
        ], $actual);
        self::assertCount(9, array_unique(array_map(static fn (SalesPermission $permission): string => $permission->id()->toString(), $definitions)));
        self::assertCount(9, array_unique(array_keys($actual)));
    }

    public function test_roles_have_exact_codes_names_and_unique_stable_identities(): void
    {
        $actual = [];
        foreach (SalesRole::cases() as $role) {
            $actual[$role->code()->toString()] = $role->name()->toString();
        }

        self::assertSame([
            'SALES_VIEWER' => 'Sales Viewer',
            'SALES_EDITOR' => 'Sales Editor',
            'SALES_MANAGER' => 'Sales Manager',
            'SALES_POSTER' => 'Sales Poster',
        ], $actual);
        self::assertCount(4, array_unique(array_map(static fn (SalesRole $role): string => $role->id()->toString(), SalesRole::cases())));
    }

    #[DataProvider('rolePermissionProvider')]
    public function test_role_permission_mapping_is_exact_and_posting_is_separate(SalesRole $role, array $expected): void
    {
        self::assertSame($expected, array_map(
            static fn (SalesPermission $permission): string => $permission->value,
            $role->permissions(),
        ));
    }

    public static function rolePermissionProvider(): array
    {
        $editor = [
            'SALES.VIEW',
            'SALES.QUOTATIONS_MANAGE',
            'SALES.ORDERS_MANAGE',
            'SALES.INVOICES_DRAFT_MANAGE',
            'SALES.CREDIT_INVOICES_DRAFT_MANAGE',
        ];

        return [
            'viewer' => [SalesRole::Viewer, ['SALES.VIEW']],
            'editor' => [SalesRole::Editor, $editor],
            'manager' => [SalesRole::Manager, [...$editor, 'SALES.INVOICES_ISSUE', 'SALES.CREDIT_INVOICES_ISSUE']],
            'poster' => [SalesRole::Poster, ['SALES.INVOICES_POST', 'SALES.CREDIT_INVOICES_POST']],
        ];
    }
}
