<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class PostOtherBankTransactionResult
{
    public function __construct(public PostOtherBankTransactionStatus $status, public ?BankTransactionId $bankTransactionId = null) {}
}
