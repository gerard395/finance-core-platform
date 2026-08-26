<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final class Payment
{
    /** @var array<string, PaymentAllocation> */
    private array $allocations = [];

    /** @param list<PaymentAllocation> $allocations */
    public function __construct(private readonly PaymentId $id, private readonly PaymentType $type, private readonly RelationId $relationId, private readonly Money $amount, array $allocations = [])
    {
        if (! $amount->isPositive()) {
            throw new DomainException('Payment amount must be positive.');
        } $this->replaceAllocations($allocations);
    }

    public function id(): PaymentId
    {
        return $this->id;
    }

    public function type(): PaymentType
    {
        return $this->type;
    }

    public function relationId(): RelationId
    {
        return $this->relationId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    /** @return list<PaymentAllocation> */
    public function allocations(): array
    {
        return array_values($this->allocations);
    }

    /** @param list<PaymentAllocation> $allocations */
    public function replaceAllocations(array $allocations): void
    {
        $indexed = [];
        $targets = [];
        foreach ($allocations as $allocation) {
            if (! $allocation->amount()->currency()->equals($this->amount->currency())) {
                throw new DomainException('Allocation currency must match Payment currency.');
            } $id = $allocation->id()->toString();
            $target = $allocation->openItemId()->toString();
            if (isset($indexed[$id]) || isset($targets[$target])) {
                throw new DomainException('Allocation identities and targets must be unique.');
            } $indexed[$id] = $allocation;
            $targets[$target] = true;
        } $this->allocations = $indexed;
    }

    public function allocationTotal(): Money
    {
        $total = Money::zero($this->amount->currency());
        foreach ($this->allocations as $allocation) {
            $total = $total->add($allocation->amount());
        }

return $total;
    }
}
