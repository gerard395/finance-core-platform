<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;

final readonly class PurchasingCreditFiscalLineInput
{
    public function __construct(
        private PurchaseCreditInvoiceLineId $purchaseCreditInvoiceLineId,
        private TaxPosting $originalTaxPosting,
        private LedgerAccountId $expenseAccountId,
        private LedgerAccountId $inputVatAccountId,
        private JournalEntryLineId $expenseReversalLineId,
        private ?JournalEntryLineId $taxReversalLineId,
        private TaxPostingId $reversalTaxPostingId,
        /** @var list<PurchasingCreditTaxLegReversalInput> */
        private array $internationalLegs = [],
    ) {}

    public function purchaseCreditInvoiceLineId(): PurchaseCreditInvoiceLineId
    {
        return $this->purchaseCreditInvoiceLineId;
    }

    public function originalTaxPosting(): TaxPosting
    {
        return $this->originalTaxPosting;
    }

    public function expenseAccountId(): LedgerAccountId
    {
        return $this->expenseAccountId;
    }

    public function inputVatAccountId(): LedgerAccountId
    {
        return $this->inputVatAccountId;
    }

    public function expenseReversalLineId(): JournalEntryLineId
    {
        return $this->expenseReversalLineId;
    }

    public function taxReversalLineId(): ?JournalEntryLineId
    {
        return $this->taxReversalLineId;
    }

    public function reversalTaxPostingId(): TaxPostingId
    {
        return $this->reversalTaxPostingId;
    }

    /** @return list<PurchasingCreditTaxLegReversalInput> */
    public function internationalLegs(): array
    {
        return $this->internationalLegs;
    }
}
