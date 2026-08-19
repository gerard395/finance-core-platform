<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;

final readonly class SalesFiscalLineInput
{
    public function __construct(
        private SalesInvoiceLineId $salesInvoiceLineId,
        private TaxCode $taxCode,
        private LedgerAccountId $revenueAccountId,
        private LedgerAccountId $outputVatAccountId,
        private JournalEntryLineId $revenueLineId,
        private ?JournalEntryLineId $taxLineId,
        private TaxPostingId $taxPostingId,
    ) {}

    public function salesInvoiceLineId(): SalesInvoiceLineId
    {
        return $this->salesInvoiceLineId;
    }

    public function taxCode(): TaxCode
    {
        return $this->taxCode;
    }

    public function revenueAccountId(): LedgerAccountId
    {
        return $this->revenueAccountId;
    }

    public function outputVatAccountId(): LedgerAccountId
    {
        return $this->outputVatAccountId;
    }

    public function revenueLineId(): JournalEntryLineId
    {
        return $this->revenueLineId;
    }

    public function taxLineId(): ?JournalEntryLineId
    {
        return $this->taxLineId;
    }

    public function taxPostingId(): TaxPostingId
    {
        return $this->taxPostingId;
    }
}
