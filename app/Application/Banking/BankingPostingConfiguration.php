<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;

final readonly class BankingPostingConfiguration
{
    public function __construct(public AdministrationId $administrationId, public AdministrationBankAccountId $bankAccountId, public JournalId $bankJournalId, public LedgerAccountId $bankLedgerAccountId) {}
}
