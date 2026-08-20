<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Definitions;

use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\RelationsRole;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\RoleCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RelationsAuthorizationDefinitionsTest extends TestCase
{
    public function test_permission_definitions_have_canonical_codes_names_and_unique_stable_identities(): void
    {
        $definitions = RelationsPermission::cases();

        self::assertCount(5, $definitions);
        self::assertSame([
            'RELATIONS.VIEW' => 'View Relations',
            'RELATIONS.CREATE' => 'Create Relation',
            'RELATIONS.UPDATE' => 'Change Relation',
            'RELATIONS.CLASSIFY_CUSTOMER' => 'Manage Customer Classification',
            'RELATIONS.CLASSIFY_SUPPLIER' => 'Manage Supplier Classification',
        ], $this->permissionMap($definitions));
        self::assertCount(5, array_unique(array_map(static fn (RelationsPermission $permission): string => $permission->id()->toString(), $definitions)));
        self::assertContainsOnlyInstancesOf(PermissionCode::class, array_map(static fn (RelationsPermission $permission): PermissionCode => $permission->code(), $definitions));
    }

    public function test_role_definitions_have_canonical_codes_names_and_unique_stable_identities(): void
    {
        $definitions = RelationsRole::cases();

        self::assertCount(3, $definitions);
        self::assertSame([
            'RELATIONS_VIEWER' => 'Relations Viewer',
            'RELATIONS_EDITOR' => 'Relations Editor',
            'RELATIONS_MANAGER' => 'Relations Manager',
        ], $this->roleMap($definitions));
        self::assertCount(3, array_unique(array_map(static fn (RelationsRole $role): string => $role->id()->toString(), $definitions)));
        self::assertContainsOnlyInstancesOf(RoleCode::class, array_map(static fn (RelationsRole $role): RoleCode => $role->code(), $definitions));
    }

    #[DataProvider('rolePermissionProvider')]
    public function test_role_permission_mapping_is_exact(RelationsRole $role, array $expected): void
    {
        self::assertSame($expected, array_map(
            static fn (RelationsPermission $permission): string => $permission->code()->toString(),
            $role->permissions(),
        ));
    }

    public static function rolePermissionProvider(): array
    {
        return [
            'viewer' => [RelationsRole::Viewer, ['RELATIONS.VIEW']],
            'editor' => [RelationsRole::Editor, ['RELATIONS.VIEW', 'RELATIONS.CREATE', 'RELATIONS.UPDATE']],
            'manager' => [RelationsRole::Manager, [
                'RELATIONS.VIEW',
                'RELATIONS.CREATE',
                'RELATIONS.UPDATE',
                'RELATIONS.CLASSIFY_CUSTOMER',
                'RELATIONS.CLASSIFY_SUPPLIER',
            ]],
        ];
    }

    public function test_canonical_permission_strings_are_not_duplicated_in_presentation(): void
    {
        $projectRoot = dirname(__DIR__, 5);
        foreach (['app/Http', 'app/Presentation', 'resources/views', 'routes'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot.'/'.$directory));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                foreach (RelationsPermission::cases() as $permission) {
                    self::assertStringNotContainsString($permission->value, $contents);
                }
            }
        }
    }

    /** @param list<RelationsPermission> $definitions */
    private function permissionMap(array $definitions): array
    {
        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->code()->toString()] = $definition->name()->toString();
        }

        return $map;
    }

    /** @param list<RelationsRole> $definitions */
    private function roleMap(array $definitions): array
    {
        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->code()->toString()] = $definition->name()->toString();
        }

        return $map;
    }
}
