<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entities;

use App\Domain\Identity\Entities\Role;
use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $role = $this->createRole();

        self::assertSame('FINANCE_ADMIN', $role->code()->value());
        self::assertSame('Finance administrator', $role->name()->value());
        self::assertSame('Manages financial configuration.', $role->description());
        self::assertSame(RoleStatus::Active, $role->status());
        self::assertTrue($role->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity_or_code(): void
    {
        $role = $this->createRole();
        $id = $role->id();
        $code = $role->code();

        $role->rename(new RoleName('Financial controller'));

        self::assertSame('Financial controller', $role->name()->value());
        self::assertSame($id, $role->id());
        self::assertSame($code, $role->code());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $role = $this->createRole();

        $role->deactivate();
        $role->deactivate();
        self::assertSame(RoleStatus::Inactive, $role->status());
        self::assertFalse($role->isActive());

        $role->activate();
        $role->activate();
        self::assertSame(RoleStatus::Active, $role->status());
        self::assertTrue($role->isActive());
    }

    public function test_description_may_be_null(): void
    {
        self::assertNull($this->createRole(null)->description());
    }

    public function test_empty_description_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createRole('');
    }

    private function createRole(?string $description = 'Manages financial configuration.'): Role
    {
        return new Role(
            new RoleId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new RoleCode('finance_admin'),
            new RoleName('Finance administrator'),
            $description,
            RoleStatus::Active,
        );
    }
}
