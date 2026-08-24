<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class SalesPostingConfiguration
{
    public function __construct(
        private AdministrationId $administrationId,
        private JournalId $salesJournalId,
        private LedgerAccountId $accountsReceivableLedgerAccountId,
        private LedgerAccountId $revenueLedgerAccountId,
        private LedgerAccountId $outputVatLedgerAccountId,
    ) {}

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function salesJournalId(): JournalId
    {
        return $this->salesJournalId;
    }

    public function accountsReceivableLedgerAccountId(): LedgerAccountId
    {
        return $this->accountsReceivableLedgerAccountId;
    }

    public function revenueLedgerAccountId(): LedgerAccountId
    {
        return $this->revenueLedgerAccountId;
    }

    public function outputVatLedgerAccountId(): LedgerAccountId
    {
        return $this->outputVatLedgerAccountId;
    }
}
