<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankTransactionIdentityGenerator;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelBankTransactionIdentityGenerator implements BankTransactionIdentityGenerator
{
    private function uuid(): Uuid
    {
        return new Uuid((string) Str::uuid());
    }

    public function transaction(): BankTransactionId
    {
        return new BankTransactionId($this->uuid());
    }

    public function payment(): PaymentId
    {
        return new PaymentId($this->uuid());
    }

    public function allocation(): PaymentAllocationId
    {
        return new PaymentAllocationId($this->uuid());
    }
}
