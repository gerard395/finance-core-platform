<?php

declare(strict_types=1);

namespace App\Application\Sales;

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
use App\Domain\Fiscal\Services\TaxPostingReversalPolicy;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class PostSalesCreditInvoiceWithTax
{
    public function __construct(
        private TaxPostingReversalPolicy $reversalPolicy,
        private PostingEngine $postingEngine,
    ) {}

    /**
     * @param  list<SalesCreditFiscalLineInput>  $fiscalLines
     * @param  list<TaxPosting>  $history
     */
    public function execute(
        SalesCreditInvoice $creditInvoice,
        array $fiscalLines,
        array $history,
        JournalId $salesJournalId,
        LedgerAccountId $debtorAccountId,
        JournalEntryLineId $debtorLineId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): SalesCreditFiscalPostingResult {
        $this->assertPostable($creditInvoice);
        $inputs = $this->indexInputs($creditInvoice, $fiscalLines);
        $workingHistory = array_values($history);
        $seenOriginals = [];
        $grossTotal = Money::zero($creditInvoice->currency());
        $journalLines = [];

        foreach ($creditInvoice->lines() as $line) {
            $input = $inputs[$line->id()->toString()];
            $original = $input->originalTaxPosting();
            $this->assertInput($creditInvoice, $line->lineTotal(), $input);
            $key = $original->id()->toString();
            if (isset($seenOriginals[$key])) {
                throw new DomainException('The same original tax posting cannot be reversed twice in one request.');
            }
            $seenOriginals[$key] = true;
            $this->reversalPolicy->assertCanReverseOriginal($original, $workingHistory);
            $grossTotal = $grossTotal->add($original->taxableBase())->add($original->taxAmount());
            $description = 'Sales credit invoice '.$creditInvoice->number()->value().' line '.$line->id()->toString();
            $journalLines[] = new JournalEntryLine($input->revenueReversalLineId(), $input->revenueAccountId(), $original->taxableBase(), null, $description);
            if ($original->taxAmount()->isPositive()) {
                $journalLines[] = new JournalEntryLine($input->taxReversalLineId(), $input->outputVatAccountId(), $original->taxAmount(), null, $description.' output VAT reversal');
            }
        }

        $journalLines[] = new JournalEntryLine($debtorLineId, $debtorAccountId, null, $grossTotal, 'Sales credit invoice '.$creditInvoice->number()->value());
        $request = new PostingRequest($creditInvoice->administrationId(), $salesJournalId, $postingDate, $reference, $journalLines);
        $postingResult = $this->postingEngine->post($request);
        if (! $postingResult->isSuccess()) {
            return new SalesCreditFiscalPostingResult($request, $postingResult, []);
        }
        $entry = $postingResult->journalEntry();
        if ($entry === null) {
            throw new DomainException('A successful posting result must contain a journal entry.');
        }

        $reversals = [];
        foreach ($creditInvoice->lines() as $line) {
            $input = $inputs[$line->id()->toString()];
            $original = $input->originalTaxPosting();
            if ($entry->line($input->revenueReversalLineId()) === null || ($input->taxReversalLineId() !== null && $entry->line($input->taxReversalLineId()) === null)) {
                throw new DomainException('Tax reversal references must exist in the posted journal entry.');
            }
            $reversal = new TaxPosting(
                $input->reversalTaxPostingId(), $creditInvoice->administrationId(), $original->taxCodeId(), $original->taxRate(),
                $original->taxableBase(), $original->taxAmount(), TaxPostingDirection::Output, TaxSourceDocumentType::SalesCreditInvoice,
                new TaxSourceDocumentId($creditInvoice->id()->uuid()), new TaxSourceLineId($line->id()->uuid()),
                $entry->postingDate(), $entry->id(), $input->revenueReversalLineId(), $input->taxReversalLineId(),
                TaxPostingType::Reversal, $original->id(),
            );
            $this->reversalPolicy->assertValidReversal($original, $reversal, $workingHistory);
            $workingHistory[] = $reversal;
            $reversals[] = $reversal;
        }

        return new SalesCreditFiscalPostingResult($request, $postingResult, $reversals);
    }

    private function assertPostable(SalesCreditInvoice $invoice): void
    {
        if (! in_array($invoice->status(), [SalesCreditInvoiceStatus::Finalized, SalesCreditInvoiceStatus::Posted], true)) {
            throw new DomainException('A sales credit invoice must be at least finalized before fiscal posting.');
        }
    }

    /** @param list<SalesCreditFiscalLineInput> $inputs @return array<string, SalesCreditFiscalLineInput> */
    private function indexInputs(SalesCreditInvoice $invoice, array $inputs): array
    {
        $indexed = [];
        foreach ($inputs as $input) {
            $key = $input->salesCreditInvoiceLineId()->toString();
            if (isset($indexed[$key]) || ! $invoice->hasLine($input->salesCreditInvoiceLineId())) {
                throw new DomainException('Fiscal input must reference each credit invoice line exactly once.');
            }
            $indexed[$key] = $input;
        }
        foreach ($invoice->lines() as $line) {
            if (! isset($indexed[$line->id()->toString()])) {
                throw new DomainException('Every sales credit invoice line requires fiscal input.');
            }
        }

        return $indexed;
    }

    private function assertInput(SalesCreditInvoice $invoice, Money $lineTotal, SalesCreditFiscalLineInput $input): void
    {
        $original = $input->originalTaxPosting();
        if ($original->type() !== TaxPostingType::Original || $original->direction() !== TaxPostingDirection::Output || $original->sourceDocumentType() !== TaxSourceDocumentType::SalesInvoice) {
            throw new DomainException('A sales credit reversal requires an original sales output tax posting.');
        }
        if (! $original->administrationId()->equals($invoice->administrationId()) || ! $original->taxableBase()->currency()->equals($invoice->currency())) {
            throw new DomainException('Tax posting context must match the sales credit invoice.');
        }
        if (! $lineTotal->equals($original->taxableBase())) {
            throw new DomainException('A sales credit line must fully reverse the original taxable base.');
        }
        if (($original->taxAmount()->isPositive() && $input->taxReversalLineId() === null) || ($original->taxAmount()->isZero() && $input->taxReversalLineId() !== null)) {
            throw new DomainException('Tax reversal line identity must match the original tax amount.');
        }
    }
}
