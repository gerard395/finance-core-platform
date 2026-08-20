<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class RelationListItem
{
    public function __construct(private RelationId $id, private RelationCode $code, private DisplayName $displayName, private bool $active, private bool $customer, private bool $supplier) {}

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

    public function isCustomer(): bool
    {
        return $this->customer;
    }

    public function isSupplier(): bool
    {
        return $this->supplier;
    }
}
