<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entities;

use App\Domain\Identity\Entities\Permission;
use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $permission = $this->createPermission();

        self::assertSame('ACCOUNTING.JOURNAL.POST', $permission->code()->value());
        self::assertSame('Post journal entries', $permission->name()->value());
        self::assertSame('Allows journal entries to be posted.', $permission->description());
        self::assertSame(PermissionStatus::Active, $permission->status());
        self::assertTrue($permission->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity_or_code(): void
    {
        $permission = $this->createPermission();
        $id = $permission->id();
        $code = $permission->code();

        $permission->rename(new PermissionName('Finalize journal entries'));

        self::assertSame('Finalize journal entries', $permission->name()->value());
        self::assertSame($id, $permission->id());
        self::assertSame($code, $permission->code());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $permission = $this->createPermission();

        $permission->deactivate();
        $permission->deactivate();
        self::assertSame(PermissionStatus::Inactive, $permission->status());
        self::assertFalse($permission->isActive());

        $permission->activate();
        $permission->activate();
        self::assertSame(PermissionStatus::Active, $permission->status());
        self::assertTrue($permission->isActive());
    }

    public function test_description_may_be_null(): void
    {
        self::assertNull($this->createPermission(null)->description());
    }

    public function test_empty_description_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createPermission('');
    }

    private function createPermission(?string $description = 'Allows journal entries to be posted.'): Permission
    {
        return new Permission(
            new PermissionId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new PermissionCode('accounting.journal.post'),
            new PermissionName('Post journal entries'),
            $description,
            PermissionStatus::Active,
        );
    }
}
