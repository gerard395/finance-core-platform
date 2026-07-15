<?php

declare(strict_types=1);

namespace App\Domain\Relations\ValueObjects;

use App\Domain\Shared\Identity\Uuid;

final readonly class RelationId
{
    public function __construct(
        private Uuid $uuid,
    ) {}

    public function uuid(): Uuid
    {
        return $this->uuid;
    }

    public function equals(self $other): bool
    {
        return $this->uuid->equals($other->uuid);
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
