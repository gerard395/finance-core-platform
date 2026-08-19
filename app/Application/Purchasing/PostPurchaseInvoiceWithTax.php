<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Fiscal\ValueObjects\TaxCalculationResult;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class PostPurchaseInvoiceWithTax
{
    public function __construct(
        private TaxCalculation $taxCalculation,
        private PostingEngine $postingEngine,
    ) {}

    /** @param list<PurchasingFiscalLineInput> $fiscalLines */
    public function execute(
        PurchaseInvoice $invoice,
        array $fiscalLines,
        JournalId $purchaseJournalId,
        LedgerAccountId $creditorAccountId,
        JournalEntryLineId $creditorLineId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): PurchasingFiscalPostingResult {
        $this->assertPostable($invoice);
        $inputs = $this->indexInputs($invoice, $fiscalLines);
        $calculatedLines = [];
        $grossTotal = Money::zero($invoice->currency());

        foreach ($invoice->lines() as $invoiceLine) {
            $input = $inputs[$invoiceLine->id()->toString()];
            $calculation = $this->taxCalculation->calculate($invoiceLine->lineTotal(), $input->taxCode());
            $this->assertTaxLineCombination($input, $calculation);
            $grossTotal = $grossTotal->add($calculation->grossAmount());
            $calculatedLines[] = [$invoiceLine, $input, $calculation];
        }

        $description = 'Purchase invoice '.$invoice->number()->value();
        $journalEntryLines = [];

        foreach ($calculatedLines as [$invoiceLine, $input, $calculation]) {
            $lineDescription = $description.' line '.$invoiceLine->id()->toString();
            $journalEntryLines[] = new JournalEntryLine(
                $input->expenseLineId(),
                $input->expenseAccountId(),
                $calculation->netAmount(),
                null,
                $lineDescription,
            );

            if ($calculation->taxAmount()->isPositive()) {
                $journalEntryLines[] = new JournalEntryLine(
                    $input->taxLineId(),
                    $input->inputVatAccountId(),
                    $calculation->taxAmount(),
                    null,
                    $lineDescription.' input VAT',
                );
            }
        }

        $journalEntryLines[] = new JournalEntryLine(
            $creditorLineId,
            $creditorAccountId,
            null,
            $grossTotal,
            $description,
        );

        $request = new PostingRequest(
            $invoice->administrationId(),
            $purchaseJournalId,
            $postingDate,
            $reference,
            $journalEntryLines,
        );
        $postingResult = $this->postingEngine->post($request);

        if (! $postingResult->isSuccess()) {
            return new PurchasingFiscalPostingResult($request, $postingResult, []);
        }

        $journalEntry = $postingResult->journalEntry();

        if ($journalEntry === null) {
            throw new DomainException('A successful posting result must contain a journal entry.');
        }

        $taxPostings = [];

        foreach ($calculatedLines as [$invoiceLine, $input, $calculation]) {
            if ($journalEntry->line($input->expenseLineId()) === null
                || ($input->taxLineId() !== null && $journalEntry->line($input->taxLineId()) === null)) {
                throw new DomainException('Tax posting references must exist in the posted journal entry.');
            }

            $taxPostings[] = new TaxPosting(
                $input->taxPostingId(),
                $invoice->administrationId(),
                $calculation->taxCodeId(),
                $calculation->taxRate(),
                $calculation->netAmount(),
                $calculation->taxAmount(),
                TaxPostingDirection::Input,
                TaxSourceDocumentType::PurchaseInvoice,
                new TaxSourceDocumentId($invoice->id()->uuid()),
                new TaxSourceLineId($invoiceLine->id()->uuid()),
                $journalEntry->postingDate(),
                $journalEntry->id(),
                $input->expenseLineId(),
                $input->taxLineId(),
                TaxPostingType::Original,
                null,
            );
        }

        return new PurchasingFiscalPostingResult($request, $postingResult, $taxPostings);
    }

    private function assertPostable(PurchaseInvoice $invoice): void
    {
        if (! in_array($invoice->status(), [
            PurchaseInvoiceStatus::Finalized,
            PurchaseInvoiceStatus::Posted,
            PurchaseInvoiceStatus::Paid,
        ], true)) {
            throw new DomainException('A purchase invoice must be at least finalized before fiscal posting.');
        }
    }

    /**
     * @param  list<PurchasingFiscalLineInput>  $fiscalLines
     * @return array<string, PurchasingFiscalLineInput>
     */
    private function indexInputs(PurchaseInvoice $invoice, array $fiscalLines): array
    {
        $inputs = [];

        foreach ($fiscalLines as $input) {
            $key = $input->purchaseInvoiceLineId()->toString();

            if (isset($inputs[$key])) {
                throw new DomainException('Fiscal input can occur only once per purchase invoice line.');
            }

            if (! $invoice->hasLine($input->purchaseInvoiceLineId())) {
                throw new DomainException('Fiscal input references a line outside the purchase invoice.');
            }

            $inputs[$key] = $input;
        }

        foreach ($invoice->lines() as $invoiceLine) {
            if (! isset($inputs[$invoiceLine->id()->toString()])) {
                throw new DomainException('Every purchase invoice line requires fiscal input.');
            }
        }

        return $inputs;
    }

    private function assertTaxLineCombination(
        PurchasingFiscalLineInput $input,
        TaxCalculationResult $calculation,
    ): void {
        if ($calculation->taxAmount()->isPositive() && $input->taxLineId() === null) {
            throw new DomainException('A positive tax amount requires a tax line identity.');
        }

        if ($calculation->taxAmount()->isZero() && $input->taxLineId() !== null) {
            throw new DomainException('A zero tax amount cannot have a tax line identity.');
        }
    }
}
