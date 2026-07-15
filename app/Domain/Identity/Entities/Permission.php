<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\Enums\PermissionStatus;
use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use InvalidArgumentException;

final class Permission
{
    public function __construct(
        private readonly PermissionId $id,
        private readonly PermissionCode $code,
        private PermissionName $name,
        private readonly ?string $description,
        private PermissionStatus $status,
    ) {
        self::assertValidDescription($description);
    }

    public function id(): PermissionId
    {
        return $this->id;
    }

    public function code(): PermissionCode
    {
        return $this->code;
    }

    public function name(): PermissionName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): PermissionStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === PermissionStatus::Active;
    }

    public function rename(PermissionName $name): void
    {
        $this->name = $name;
    }

    public function activate(): void
    {
        $this->status = PermissionStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = PermissionStatus::Inactive;
    }

    private static function assertValidDescription(?string $description): void
    {
        if ($description === null) {
            return;
        }

        if ($description === '') {
            throw new InvalidArgumentException('Permission description cannot be empty; use null instead.');
        }

        if (preg_match('/\A\s|\s\z/u', $description) === 1) {
            throw new InvalidArgumentException('Permission description cannot contain leading or trailing whitespace.');
        }

        if (mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('Permission description cannot exceed 1000 characters.');
        }
    }
}
