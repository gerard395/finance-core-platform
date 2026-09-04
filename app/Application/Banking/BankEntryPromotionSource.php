<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;

final readonly class BankEntryPromotionSource
{
    public function __construct(public BankStatementEntry $entry, public AdministrationBankAccountId $bankAccountId) {}
}
