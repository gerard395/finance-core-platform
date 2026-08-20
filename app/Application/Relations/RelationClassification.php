<?php

declare(strict_types=1);

namespace App\Application\Relations;

final readonly class RelationClassification
{
    public function __construct(
        private bool $customer,
        private bool $supplier,
    ) {}

    public function isCustomer(): bool
    {
        return $this->customer;
    }

    public function isSupplier(): bool
    {
        return $this->supplier;
    }
}
