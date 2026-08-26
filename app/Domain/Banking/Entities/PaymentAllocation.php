<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class PaymentAllocation
{
    public function __construct(private PaymentAllocationId $id, private OpenItemId $openItemId, private Money $amount, private ?OpenItemType $openItemType = null, private ?OpenItemSide $openItemSide = null, private ?RelationId $relationId = null, private ?LedgerAccountId $controlLedgerAccountId = null)
    {
        if (! $amount->isPositive()) {
            throw new DomainException('Payment allocation amount must be positive.');
        }
    }

    public function id(): PaymentAllocationId
    {
        return $this->id;
    }

    public function openItemId(): OpenItemId
    {
        return $this->openItemId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function openItemType(): ?OpenItemType
    {
        return $this->openItemType;
    }

    public function openItemSide(): ?OpenItemSide
    {
        return $this->openItemSide;
    }

    public function relationId(): ?RelationId
    {
        return $this->relationId;
    }

    public function controlLedgerAccountId(): ?LedgerAccountId
    {
        return $this->controlLedgerAccountId;
    }

    public function finalized(OpenItemType $type, OpenItemSide $side, RelationId $relationId, LedgerAccountId $control): self
    {
        return new self($this->id, $this->openItemId, $this->amount, $type, $side, $relationId, $control);
    }

    public function isFinalized(): bool
    {
        return $this->openItemType !== null && $this->openItemSide !== null && $this->relationId !== null && $this->controlLedgerAccountId !== null;
    }
}
