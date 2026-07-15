<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\RelationId;

final class Customer
{
    public function __construct(
        private readonly CustomerId $id,
        private readonly RelationId $relationId,
        private readonly CustomerNumber $customerNumber,
        private bool $active,
    ) {}

    public function id(): CustomerId
    {
        return $this->id;
    }

    public function relationId(): RelationId
    {
        return $this->relationId;
    }

    public function customerNumber(): CustomerNumber
    {
        return $this->customerNumber;
    }

    public function isActive(): bool
    {
        return $this->active;
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
