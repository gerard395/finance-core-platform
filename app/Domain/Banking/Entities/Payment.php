<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class Payment
{
    public function __construct(
        private PaymentId $id,
        private OpenItemId $openItemId,
        private Money $amount,
    ) {
        if ($amount->isZero() || str_starts_with($amount->amount(), '-')) {
            throw new DomainException('Payment amount must be greater than zero.');
        }
    }

    public function id(): PaymentId
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
}
