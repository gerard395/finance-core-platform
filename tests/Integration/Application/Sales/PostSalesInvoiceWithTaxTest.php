<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\CreateSalesInvoicePostingRequest;
use App\Application\Sales\PostSalesInvoiceWithTax;
use App\Application\Sales\SalesFiscalLineInput;
use App\Application\Sales\SalesFiscalPostingResult;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Fiscal\Services\TaxPostingIdentityPolicy;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostSalesInvoiceWithTaxTest extends TestCase
{
    private const string POSTED_ENTRY_ID = '90000000-0000-4000-8000-000000000001';

    private int $identitySequence = 1;

    public function test_one_line_at_twenty_one_percent_posts_net_tax_and_gross_with_complete_trace(): void
    {
        $invoice = $this->invoiceWithLines([['2', '50']]);
        $input = $this->input($invoice->lines()[0], '21');

        $result = $this->execute($invoice, [$input]);

        self::assertTrue($result->postingResult()->isSuccess());
        self::assertTrue((new PostingValidation)->validate($result->postingRequest())->isValid());
        self::assertCount(3, $result->postingRequest()->lines());
        $entry = $result->postingResult()->journalEntry();
        self::assertNotNull($entry);
        self::assertTrue($entry->isPosted());
        self::assertSame(self::POSTED_ENTRY_ID, $entry->id()->toString());

        $debtor = $entry->lines()[0];
        $revenue = $entry->line($input->revenueLineId());
        $tax = $entry->line($input->taxLineId());
        self::assertSame('121', $debtor->debit()?->amount());
        self::assertSame('100', $revenue?->credit()?->amount());
        self::assertSame('21', $tax?->credit()?->amount());
        self::assertSame('EUR', $debtor->debit()?->currency()->code());
        self::assertSame('EUR', $revenue?->credit()?->currency()->code());
        self::assertSame('EUR', $tax?->credit()?->currency()->code());

        self::assertCount(1, $result->taxPostings());
        $taxPosting = $result->taxPostings()[0];
        self::assertSame(self::POSTED_ENTRY_ID, $taxPosting->journalEntryId()->toString());
        self::assertSame($input->revenueLineId(), $taxPosting->baseJournalEntryLineId());
        self::assertSame($input->taxLineId(), $taxPosting->taxJournalEntryLineId());
        self::assertSame($input->taxCode()->id(), $taxPosting->taxCodeId());
        self::assertSame('21', $taxPosting->taxRate()->value());
        self::assertSame('100', $taxPosting->taxableBase()->amount());
        self::assertSame('21', $taxPosting->taxAmount()->amount());
        self::assertSame(TaxPostingDirection::Output, $taxPosting->direction());
        self::assertSame(TaxPostingType::Original, $taxPosting->type());
        self::assertSame(TaxSourceDocumentType::SalesInvoice, $taxPosting->sourceDocumentType());
        self::assertSame($invoice->id()->toString(), $taxPosting->sourceDocumentId()->toString());
        self::assertSame($invoice->lines()[0]->id()->toString(), $taxPosting->sourceLineId()->toString());
        self::assertSame($result->postingRequest()->postingDate(), $taxPosting->postingDate());
        self::assertNull($taxPosting->reversedTaxPostingId());
    }

    public function test_one_line_at_nine_percent_uses_rate_snapshot_and_exact_amounts(): void
    {
        $invoice = $this->invoiceWithLines([['1', '200']]);
        $input = $this->input($invoice->lines()[0], '9');

        $result = $this->execute($invoice, [$input]);
        $posting = $result->taxPostings()[0];

        self::assertSame('218', $result->postingResult()->journalEntry()?->lines()[0]->debit()?->amount());
        self::assertSame('200', $posting->taxableBase()->amount());
        self::assertSame('18', $posting->taxAmount()->amount());
        self::assertSame('9', $posting->taxRate()->value());
        self::assertSame($input->taxCode()->id(), $posting->taxCodeId());
    }

    public function test_zero_percent_creates_no_tax_line_but_retains_fiscal_truth(): void
    {
        $invoice = $this->invoiceWithLines([['1', '100']]);
        $input = $this->input($invoice->lines()[0], '0', includeTaxLine: false);

        $result = $this->execute($invoice, [$input]);
        $entry = $result->postingResult()->journalEntry();
        self::assertNotNull($entry);

        self::assertCount(2, $entry->lines());
        self::assertSame('100', $entry->lines()[0]->debit()?->amount());
        self::assertSame('100', $entry->line($input->revenueLineId())?->credit()?->amount());
        self::assertNull($input->taxLineId());
        self::assertCount(1, $result->taxPostings());
        self::assertTrue($result->taxPostings()[0]->taxAmount()->isZero());
        self::assertSame('0', $result->taxPostings()[0]->taxRate()->value());
        self::assertNull($result->taxPostings()[0]->taxJournalEntryLineId());
        self::assertSame($input->revenueLineId(), $result->taxPostings()[0]->baseJournalEntryLineId());
    }

    public function test_multiple_lines_with_same_rate_create_individual_revenue_tax_and_trace_lines(): void
    {
        $invoice = $this->invoiceWithLines([['1', '100'], ['2', '25']]);
        $inputs = [
            $this->input($invoice->lines()[0], '21'),
            $this->input($invoice->lines()[1], '21'),
        ];

        $result = $this->execute($invoice, $inputs);
        $entry = $result->postingResult()->journalEntry();
        self::assertNotNull($entry);

        self::assertCount(5, $entry->lines());
        self::assertSame('181.5', $entry->lines()[0]->debit()?->amount());
        self::assertSame(['100', '50'], array_map(
            static fn (SalesFiscalLineInput $input): ?string => $entry->line($input->revenueLineId())?->credit()?->amount(),
            $inputs,
        ));
        self::assertSame(['21', '10.5'], array_map(
            static fn (SalesFiscalLineInput $input): ?string => $entry->line($input->taxLineId())?->credit()?->amount(),
            $inputs,
        ));
        self::assertCount(2, $result->taxPostings());
    }

    public function test_multiple_tax_codes_preserve_per_line_classification(): void
    {
        $invoice = $this->invoiceWithLines([['1', '100'], ['1', '100']]);
        $inputs = [
            $this->input($invoice->lines()[0], '21'),
            $this->input($invoice->lines()[1], '9'),
        ];

        $result = $this->execute($invoice, $inputs);

        self::assertSame('230', $result->postingResult()->journalEntry()?->lines()[0]->debit()?->amount());
        self::assertSame(['21', '9'], array_map(
            static fn ($posting): string => $posting->taxRate()->value(),
            $result->taxPostings(),
        ));
        self::assertSame(
            array_map(static fn (SalesFiscalLineInput $input): string => $input->taxCode()->id()->toString(), $inputs),
            array_map(static fn ($posting): string => $posting->taxCodeId()->toString(), $result->taxPostings()),
        );
    }

    #[DataProvider('rejectedStatuses')]
    public function test_draft_and_cancelled_invoices_are_rejected(SalesInvoiceStatus $status): void
    {
        $invoice = $this->invoiceWithLines([['1', '100']], finalize: false);

        if ($status === SalesInvoiceStatus::Cancelled) {
            $invoice->cancel();
        }

        $this->expectException(DomainException::class);
        $this->execute($invoice, [$this->input($invoice->lines()[0], '21')]);
    }

    /** @return array<string, array{SalesInvoiceStatus}> */
    public static function rejectedStatuses(): array
    {
        return [
            'draft' => [SalesInvoiceStatus::Draft],
            'cancelled' => [SalesInvoiceStatus::Cancelled],
        ];
    }

    public function test_positive_tax_requires_tax_line_and_zero_tax_rejects_tax_line(): void
    {
        $positiveInvoice = $this->invoiceWithLines([['1', '100']]);

        try {
            $this->execute($positiveInvoice, [$this->input($positiveInvoice->lines()[0], '21', includeTaxLine: false)]);
            self::fail('Positive tax without a line ID must be rejected.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $zeroInvoice = $this->invoiceWithLines([['1', '100']]);
        $this->expectException(DomainException::class);
        $this->execute($zeroInvoice, [$this->input($zeroInvoice->lines()[0], '0')]);
    }

    public function test_failed_posting_returns_no_tax_postings(): void
    {
        $invoice = $this->invoiceWithLines([['1', '100']]);
        $duplicateId = $this->journalEntryLineId();
        $input = $this->input($invoice->lines()[0], '21', revenueLineId: $duplicateId);

        $result = $this->execute($invoice, [$input], debtorLineId: $duplicateId);

        self::assertFalse($result->postingResult()->isSuccess());
        self::assertNull($result->postingResult()->journalEntry());
        self::assertSame([], $result->taxPostings());
        self::assertSame('duplicate_line_id', $result->postingResult()->validationErrors()[0]->code());
    }

    public function test_duplicate_tax_posting_identity_in_request_is_rejected(): void
    {
        $invoice = $this->invoiceWithLines([['1', '100'], ['1', '100']]);
        $id = new TaxPostingId($this->nextUuid('8'));
        $this->expectException(DomainException::class);
        $this->execute($invoice, [$this->input($invoice->lines()[0], '21', taxPostingId: $id), $this->input($invoice->lines()[1], '21', taxPostingId: $id)]);
    }

    /** @param list<SalesFiscalLineInput> $inputs */
    private function execute(
        SalesInvoice $invoice,
        array $inputs,
        ?JournalEntryLineId $debtorLineId = null,
    ): SalesFiscalPostingResult {
        $validation = new PostingValidation;
        $useCase = new PostSalesInvoiceWithTax(
            new TaxCalculation,
            new TaxPostingIdentityPolicy,
            new PostingEngine(
                $validation,
                static fn (): JournalEntryId => new JournalEntryId(new Uuid(self::POSTED_ENTRY_ID)),
            ),
            new CreateSalesInvoicePostingRequest,
        );

        return $useCase->execute(
            $invoice,
            $inputs,
            [],
            new JournalId($this->nextUuid('6')),
            new LedgerAccountId($this->nextUuid('7')),
            $debtorLineId ?? $this->journalEntryLineId(),
            new PostingDate(new DateTimeImmutable('2026-08-19')),
            new JournalEntryReference('SINV-FISCAL-001'),
        );
    }

    /** @param list<array{string, string}> $lines */
    private function invoiceWithLines(array $lines, bool $finalize = true): SalesInvoice
    {
        $invoice = new SalesInvoice(
            new SalesInvoiceId($this->nextUuid('1')),
            new SalesInvoiceNumber('sinv-fiscal-001'),
            new AdministrationId($this->nextUuid('2')),
            new CustomerId($this->nextUuid('3')),
            new Currency('EUR'),
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-31'),
            null,
            SalesInvoiceStatus::Draft,
        );

        foreach ($lines as [$quantity, $unitPrice]) {
            $invoice->addLine(new SalesInvoiceLine(
                new SalesInvoiceLineId($this->nextUuid('4')),
                new LineDescription('Fiscal sales line'),
                new Quantity($quantity),
                new Money($unitPrice, new Currency('EUR')),
            ));
        }

        if ($finalize) {
            $invoice->finalize();
        }

        return $invoice;
    }

    private function input(
        SalesInvoiceLine $line,
        string $rate,
        bool $includeTaxLine = true,
        ?JournalEntryLineId $revenueLineId = null,
        ?TaxPostingId $taxPostingId = null,
    ): SalesFiscalLineInput {
        return new SalesFiscalLineInput(
            $line->id(),
            new TaxCode(
                new TaxCodeId($this->nextUuid('5')),
                new TaxCodeCode('vat'.$rate),
                new TaxCodeName('VAT '.$rate.'%'),
                new TaxRate($rate),
                TaxPostingDirection::Output,
                TaxCodeStatus::Active,
            ),
            new LedgerAccountId($this->nextUuid('7')),
            new LedgerAccountId($this->nextUuid('7')),
            $revenueLineId ?? $this->journalEntryLineId(),
            $includeTaxLine ? $this->journalEntryLineId() : null,
            $taxPostingId ?? new TaxPostingId($this->nextUuid('8')),
        );
    }

    private function journalEntryLineId(): JournalEntryLineId
    {
        return new JournalEntryLineId($this->nextUuid('9'));
    }

    private function nextUuid(string $prefix): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $this->identitySequence++));
    }
}
