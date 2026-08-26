<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Shared\Finance\Money;

interface BankTransactionPostingRepository
{
    public function exists(AdministrationId $admin, BankTransactionId $id): bool;

    public function find(AdministrationId $admin, BankTransactionId $id): ?BankTransactionPosting;

    public function settlementAmount(AdministrationId $admin, PaymentAllocationId $id): ?Money;

    public function append(BankTransactionPostingId $id, AdministrationId $admin, BankTransactionId $tx, JournalEntryId $entry, PostingDate $date): void;
}
