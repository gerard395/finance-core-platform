<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;

final readonly class SalesCreditFiscalLineInput
{
    public function __construct(
        private SalesCreditInvoiceLineId $salesCreditInvoiceLineId,
        private TaxPosting $originalTaxPosting,
        private LedgerAccountId $revenueAccountId,
        private LedgerAccountId $outputVatAccountId,
        private JournalEntryLineId $revenueReversalLineId,
        private ?JournalEntryLineId $taxReversalLineId,
        private TaxPostingId $reversalTaxPostingId,
    ) {}

    public function salesCreditInvoiceLineId(): SalesCreditInvoiceLineId
    {
        return $this->salesCreditInvoiceLineId;
    }

    public function originalTaxPosting(): TaxPosting
    {
        return $this->originalTaxPosting;
    }

    public function revenueAccountId(): LedgerAccountId
    {
        return $this->revenueAccountId;
    }

    public function outputVatAccountId(): LedgerAccountId
    {
        return $this->outputVatAccountId;
    }

    public function revenueReversalLineId(): JournalEntryLineId
    {
        return $this->revenueReversalLineId;
    }

    public function taxReversalLineId(): ?JournalEntryLineId
    {
        return $this->taxReversalLineId;
    }

    public function reversalTaxPostingId(): TaxPostingId
    {
        return $this->reversalTaxPostingId;
    }
}
