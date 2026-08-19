<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;

final readonly class PurchasingFiscalLineInput
{
    public function __construct(
        private PurchaseInvoiceLineId $purchaseInvoiceLineId,
        private TaxCode $taxCode,
        private LedgerAccountId $expenseAccountId,
        private LedgerAccountId $inputVatAccountId,
        private JournalEntryLineId $expenseLineId,
        private ?JournalEntryLineId $taxLineId,
        private TaxPostingId $taxPostingId,
    ) {}

    public function purchaseInvoiceLineId(): PurchaseInvoiceLineId
    {
        return $this->purchaseInvoiceLineId;
    }

    public function taxCode(): TaxCode
    {
        return $this->taxCode;
    }

    public function expenseAccountId(): LedgerAccountId
    {
        return $this->expenseAccountId;
    }

    public function inputVatAccountId(): LedgerAccountId
    {
        return $this->inputVatAccountId;
    }

    public function expenseLineId(): JournalEntryLineId
    {
        return $this->expenseLineId;
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
