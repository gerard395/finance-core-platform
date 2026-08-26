<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\PaymentId;

interface BankTransactionIdentityGenerator
{
    public function transaction(): BankTransactionId;

    public function payment(): PaymentId;

    public function allocation(): PaymentAllocationId;
}
