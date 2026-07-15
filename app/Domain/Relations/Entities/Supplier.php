<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;

final class Supplier
{
    public function __construct(
        private readonly SupplierId $id,
        private readonly RelationId $relationId,
        private readonly SupplierNumber $supplierNumber,
        private bool $active,
    ) {}

    public function id(): SupplierId
    {
        return $this->id;
    }

    public function relationId(): RelationId
    {
        return $this->relationId;
    }

    public function supplierNumber(): SupplierNumber
    {
        return $this->supplierNumber;
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
