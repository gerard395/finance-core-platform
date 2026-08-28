<?php

declare(strict_types=1);

namespace App\Application\Banking;

final readonly class ReverseBankTransactionResult
{
    public function __construct(public ReverseBankTransactionStatus $status, public ?ReverseBankTransactionSuccess $success = null) {}
}
