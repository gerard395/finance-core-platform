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
use App\Domain\Fiscal\Services\TaxPostingIdentityPolicy;
use App\Domain\Fiscal\Services\TaxPostingReversalPolicy;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class PostPurchaseCreditInvoiceWithTax
{
    public function __construct(
        private TaxPostingReversalPolicy $reversalPolicy,
        private TaxPostingIdentityPolicy $identityPolicy,
        private PostingEngine $postingEngine,
    ) {}

    /**
     * @param  list<PurchasingCreditFiscalLineInput>  $fiscalLines
     * @param  list<TaxPosting>  $history
     */
    public function execute(
        PurchaseCreditInvoice $creditInvoice,
        array $fiscalLines,
        array $history,
        JournalId $purchaseJournalId,
        LedgerAccountId $creditorAccountId,
        JournalEntryLineId $creditorLineId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): PurchasingCreditFiscalPostingResult {
        $this->assertPostable($creditInvoice);
        $inputs = $this->indexInputs($creditInvoice, $fiscalLines);
        $workingHistory = array_values($history);
        $seenOriginals = [];
        $newIds = [];
        $grossTotal = Money::zero($creditInvoice->currency());
        $journalLines = [];

        foreach ($creditInvoice->lines() as $line) {
            $input = $inputs[$line->id()->toString()];
            $original = $input->originalTaxPosting();
            $this->identityPolicy->assertNewIdAvailable($input->reversalTaxPostingId(), $workingHistory);
            if (isset($newIds[$input->reversalTaxPostingId()->toString()])) {
                throw new DomainException('Tax posting identity can occur only once per request.');
            }
            $newIds[$input->reversalTaxPostingId()->toString()] = true;
            $this->assertInput($creditInvoice, $line->lineTotal(), $input);
            $key = $original->id()->toString();
            if (isset($seenOriginals[$key])) {
                throw new DomainException('The same original tax posting cannot be reversed twice in one request.');
            }
            $seenOriginals[$key] = true;
            $this->reversalPolicy->assertCanReverseOriginal($original, $workingHistory);
            $grossTotal = $grossTotal->add($original->taxableBase())->add($original->taxAmount());
            $description = 'Purchase credit invoice '.$creditInvoice->number()->value().' line '.$line->id()->toString();
            $journalLines[] = new JournalEntryLine($input->expenseReversalLineId(), $input->expenseAccountId(), null, $original->taxableBase(), $description);
            if ($original->taxAmount()->isPositive()) {
                $journalLines[] = new JournalEntryLine($input->taxReversalLineId(), $input->inputVatAccountId(), null, $original->taxAmount(), $description.' input VAT reversal');
            }
        }

        $journalLines[] = new JournalEntryLine($creditorLineId, $creditorAccountId, $grossTotal, null, 'Purchase credit invoice '.$creditInvoice->number()->value());
        $request = new PostingRequest($creditInvoice->administrationId(), $purchaseJournalId, $postingDate, $reference, $journalLines);
        $postingResult = $this->postingEngine->post($request);
        if (! $postingResult->isSuccess()) {
            return new PurchasingCreditFiscalPostingResult($request, $postingResult, []);
        }
        $entry = $postingResult->journalEntry();
        if ($entry === null) {
            throw new DomainException('A successful posting result must contain a journal entry.');
        }

        $reversals = [];
        foreach ($creditInvoice->lines() as $line) {
            $input = $inputs[$line->id()->toString()];
            $original = $input->originalTaxPosting();
            if ($entry->line($input->expenseReversalLineId()) === null || ($input->taxReversalLineId() !== null && $entry->line($input->taxReversalLineId()) === null)) {
                throw new DomainException('Tax reversal references must exist in the posted journal entry.');
            }
            $reversal = new TaxPosting(
                $input->reversalTaxPostingId(), $creditInvoice->administrationId(), $original->taxCodeId(), $original->taxRate(),
                $original->taxableBase(), $original->taxAmount(), TaxPostingDirection::Input, TaxSourceDocumentType::PurchaseCreditInvoice,
                new TaxSourceDocumentId($creditInvoice->id()->uuid()), new TaxSourceLineId($line->id()->uuid()),
                $entry->postingDate(), $entry->id(), $input->expenseReversalLineId(), $input->taxReversalLineId(),
                TaxPostingType::Reversal, $original->id(),
            );
            $this->reversalPolicy->assertValidReversal($original, $reversal, $workingHistory);
            $workingHistory[] = $reversal;
            $reversals[] = $reversal;
        }

        return new PurchasingCreditFiscalPostingResult($request, $postingResult, $reversals);
    }

    private function assertPostable(PurchaseCreditInvoice $invoice): void
    {
        if (! in_array($invoice->status(), [PurchaseCreditInvoiceStatus::Finalized, PurchaseCreditInvoiceStatus::Posted], true)) {
            throw new DomainException('A purchase credit invoice must be at least finalized before fiscal posting.');
        }
    }

    /** @param list<PurchasingCreditFiscalLineInput> $inputs @return array<string, PurchasingCreditFiscalLineInput> */
    private function indexInputs(PurchaseCreditInvoice $invoice, array $inputs): array
    {
        $indexed = [];
        foreach ($inputs as $input) {
            $key = $input->purchaseCreditInvoiceLineId()->toString();
            if (isset($indexed[$key]) || ! $invoice->hasLine($input->purchaseCreditInvoiceLineId())) {
                throw new DomainException('Fiscal input must reference each credit invoice line exactly once.');
            }
            $indexed[$key] = $input;
        }
        foreach ($invoice->lines() as $line) {
            if (! isset($indexed[$line->id()->toString()])) {
                throw new DomainException('Every purchase credit invoice line requires fiscal input.');
            }
        }

        return $indexed;
    }

    private function assertInput(PurchaseCreditInvoice $invoice, Money $lineTotal, PurchasingCreditFiscalLineInput $input): void
    {
        $original = $input->originalTaxPosting();
        if ($original->type() !== TaxPostingType::Original || $original->direction() !== TaxPostingDirection::Input || $original->sourceDocumentType() !== TaxSourceDocumentType::PurchaseInvoice) {
            throw new DomainException('A purchase credit reversal requires an original purchase input tax posting.');
        }
        if (! $original->administrationId()->equals($invoice->administrationId()) || ! $original->taxableBase()->currency()->equals($invoice->currency())) {
            throw new DomainException('Tax posting context must match the purchase credit invoice.');
        }
        if (! $lineTotal->equals($original->taxableBase())) {
            throw new DomainException('A purchase credit line must fully reverse the original taxable base.');
        }
        if (($original->taxAmount()->isPositive() && $input->taxReversalLineId() === null) || ($original->taxAmount()->isZero() && $input->taxReversalLineId() !== null)) {
            throw new DomainException('Tax reversal line identity must match the original tax amount.');
        }
    }
}
