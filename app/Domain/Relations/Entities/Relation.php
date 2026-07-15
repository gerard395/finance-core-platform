<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;

final class Relation
{
    public function __construct(
        private readonly RelationId $id,
        private readonly RelationCode $code,
        private DisplayName $displayName,
        private bool $active,
    ) {}

    public function id(): RelationId
    {
        return $this->id;
    }

    public function code(): RelationCode
    {
        return $this->code;
    }

    public function displayName(): DisplayName
    {
        return $this->displayName;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function rename(DisplayName $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
