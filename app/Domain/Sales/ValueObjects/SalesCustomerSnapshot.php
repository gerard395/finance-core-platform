<?php

declare(strict_types=1);

namespace App\Domain\Sales\ValueObjects;

use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class SalesCustomerSnapshot
{
    public function __construct(
        private CustomerId $customerId,
        private RelationId $relationId,
        private CustomerNumber $customerNumber,
        private DisplayName $displayName,
    ) {}

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function relationId(): RelationId
    {
        return $this->relationId;
    }

    public function customerNumber(): CustomerNumber
    {
        return $this->customerNumber;
    }

    public function displayName(): DisplayName
    {
        return $this->displayName;
    }
}
