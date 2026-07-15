<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\Enums\RoleStatus;
use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use InvalidArgumentException;

final class Role
{
    public function __construct(
        private readonly RoleId $id,
        private readonly RoleCode $code,
        private RoleName $name,
        private readonly ?string $description,
        private RoleStatus $status,
    ) {
        self::assertValidDescription($description);
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function code(): RoleCode
    {
        return $this->code;
    }

    public function name(): RoleName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): RoleStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === RoleStatus::Active;
    }

    public function rename(RoleName $name): void
    {
        $this->name = $name;
    }

    public function activate(): void
    {
        $this->status = RoleStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = RoleStatus::Inactive;
    }

    private static function assertValidDescription(?string $description): void
    {
        if ($description === null) {
            return;
        }

        if ($description === '') {
            throw new InvalidArgumentException('Role description cannot be empty; use null instead.');
        }

        if (preg_match('/\A\s|\s\z/u', $description) === 1) {
            throw new InvalidArgumentException('Role description cannot contain leading or trailing whitespace.');
        }

        if (mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('Role description cannot exceed 1000 characters.');
        }
    }
}
