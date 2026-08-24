<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\Entities\JournalEntryLine;
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
use App\Domain\Fiscal\Services\TaxPostingIdentityPolicy;
use App\Domain\Fiscal\ValueObjects\TaxCalculationResult;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class PostSalesInvoiceWithTax
{
    public function __construct(
        private TaxCalculation $taxCalculation,
        private TaxPostingIdentityPolicy $identityPolicy,
        private PostingEngine $postingEngine,
        private CreateSalesInvoicePostingRequest $postingRequestFactory,
    ) {}

    /** @param list<SalesFiscalLineInput> $fiscalLines */
    public function execute(
        SalesInvoice $invoice,
        array $fiscalLines,
        array $history,
        JournalId $salesJournalId,
        LedgerAccountId $debtorAccountId,
        JournalEntryLineId $debtorLineId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): SalesFiscalPostingResult {
        $this->assertPostable($invoice);
        $inputs = $this->indexInputs($invoice, $fiscalLines);
        $calculatedLines = [];
        $grossTotal = Money::zero($invoice->currency());
        $newIds = [];

        foreach ($invoice->lines() as $invoiceLine) {
            $input = $inputs[$invoiceLine->id()->toString()];
            $this->identityPolicy->assertNewIdAvailable($input->taxPostingId(), $history);
            if (isset($newIds[$input->taxPostingId()->toString()])) {
                throw new DomainException('Tax posting identity can occur only once per request.');
            }
            $newIds[$input->taxPostingId()->toString()] = true;
            $calculation = $this->taxCalculation->calculate($invoiceLine->lineTotal(), $input->taxCode());
            $this->assertTaxLineCombination($input, $calculation);
            $grossTotal = $grossTotal->add($calculation->grossAmount());
            $calculatedLines[] = [$invoiceLine, $input, $calculation];
        }

        $description = 'Sales invoice '.$invoice->number()->value();
        $journalEntryLines = [
            new JournalEntryLine($debtorLineId, $debtorAccountId, $grossTotal, null, $description),
        ];

        foreach ($calculatedLines as [$invoiceLine, $input, $calculation]) {
            $lineDescription = $description.' line '.$invoiceLine->id()->toString();
            $journalEntryLines[] = new JournalEntryLine(
                $input->revenueLineId(),
                $input->revenueAccountId(),
                null,
                $calculation->netAmount(),
                $lineDescription,
            );

            if ($calculation->taxAmount()->isPositive()) {
                $journalEntryLines[] = new JournalEntryLine(
                    $input->taxLineId(),
                    $input->outputVatAccountId(),
                    null,
                    $calculation->taxAmount(),
                    $lineDescription.' output VAT',
                );
            }
        }

        $request = $this->postingRequestFactory->executeForFinancialLines(
            $invoice,
            $salesJournalId,
            $journalEntryLines,
            $postingDate,
            $reference,
        );
        $postingResult = $this->postingEngine->post($request);

        if (! $postingResult->isSuccess()) {
            return new SalesFiscalPostingResult($request, $postingResult, []);
        }

        $journalEntry = $postingResult->journalEntry();

        if ($journalEntry === null) {
            throw new DomainException('A successful posting result must contain a journal entry.');
        }

        $taxPostings = [];

        foreach ($calculatedLines as [$invoiceLine, $input, $calculation]) {
            if ($journalEntry->line($input->revenueLineId()) === null
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
                TaxPostingDirection::Output,
                TaxSourceDocumentType::SalesInvoice,
                new TaxSourceDocumentId($invoice->id()->uuid()),
                new TaxSourceLineId($invoiceLine->id()->uuid()),
                $journalEntry->postingDate(),
                $journalEntry->id(),
                $input->revenueLineId(),
                $input->taxLineId(),
                TaxPostingType::Original,
                null,
            );
        }

        return new SalesFiscalPostingResult($request, $postingResult, $taxPostings);
    }

    private function assertPostable(SalesInvoice $invoice): void
    {
        if (! in_array($invoice->status(), [
            SalesInvoiceStatus::Finalized,
            SalesInvoiceStatus::Posted,
            SalesInvoiceStatus::Paid,
        ], true)) {
            throw new DomainException('A sales invoice must be at least finalized before fiscal posting.');
        }
    }

    /**
     * @param  list<SalesFiscalLineInput>  $fiscalLines
     * @return array<string, SalesFiscalLineInput>
     */
    private function indexInputs(SalesInvoice $invoice, array $fiscalLines): array
    {
        $inputs = [];

        foreach ($fiscalLines as $input) {
            $key = $input->salesInvoiceLineId()->toString();

            if (isset($inputs[$key])) {
                throw new DomainException('Fiscal input can occur only once per sales invoice line.');
            }

            if (! $invoice->hasLine($input->salesInvoiceLineId())) {
                throw new DomainException('Fiscal input references a line outside the sales invoice.');
            }

            $inputs[$key] = $input;
        }

        foreach ($invoice->lines() as $invoiceLine) {
            if (! isset($inputs[$invoiceLine->id()->toString()])) {
                throw new DomainException('Every sales invoice line requires fiscal input.');
            }
        }

        return $inputs;
    }

    private function assertTaxLineCombination(
        SalesFiscalLineInput $input,
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
