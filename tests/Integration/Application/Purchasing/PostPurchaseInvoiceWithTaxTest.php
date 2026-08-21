<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Purchasing;

use App\Application\Purchasing\PostPurchaseInvoiceWithTax;
use App\Application\Purchasing\PurchasingFiscalLineInput;
use App\Application\Purchasing\PurchasingFiscalPostingResult;
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
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Entities\PurchaseInvoiceLine;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceNumber;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostPurchaseInvoiceWithTaxTest extends TestCase
{
    private const string POSTED_ENTRY_ID = 'a0000000-0000-4000-8000-000000000001';

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

        $expense = $entry->line($input->expenseLineId());
        $tax = $entry->line($input->taxLineId());
        $creditor = $entry->lines()[2];
        self::assertSame('100', $expense?->debit()?->amount());
        self::assertSame('21', $tax?->debit()?->amount());
        self::assertSame('121', $creditor->credit()?->amount());
        self::assertSame('EUR', $expense?->debit()?->currency()->code());
        self::assertSame('EUR', $tax?->debit()?->currency()->code());
        self::assertSame('EUR', $creditor->credit()?->currency()->code());

        self::assertCount(1, $result->taxPostings());
        $posting = $result->taxPostings()[0];
        self::assertSame(self::POSTED_ENTRY_ID, $posting->journalEntryId()->toString());
        self::assertSame($input->expenseLineId(), $posting->baseJournalEntryLineId());
        self::assertSame($input->taxLineId(), $posting->taxJournalEntryLineId());
        self::assertSame($input->taxCode()->id(), $posting->taxCodeId());
        self::assertSame('21', $posting->taxRate()->value());
        self::assertSame('100', $posting->taxableBase()->amount());
        self::assertSame('21', $posting->taxAmount()->amount());
        self::assertSame(TaxPostingDirection::Input, $posting->direction());
        self::assertSame(TaxPostingType::Original, $posting->type());
        self::assertSame(TaxSourceDocumentType::PurchaseInvoice, $posting->sourceDocumentType());
        self::assertSame($invoice->id()->toString(), $posting->sourceDocumentId()->toString());
        self::assertSame($invoice->lines()[0]->id()->toString(), $posting->sourceLineId()->toString());
        self::assertSame($result->postingRequest()->postingDate(), $posting->postingDate());
        self::assertNull($posting->reversedTaxPostingId());
    }

    public function test_one_line_at_nine_percent_uses_rate_snapshot_and_exact_amounts(): void
    {
        $invoice = $this->invoiceWithLines([['1', '200']]);
        $input = $this->input($invoice->lines()[0], '9');

        $result = $this->execute($invoice, [$input]);
        $posting = $result->taxPostings()[0];

        self::assertSame('218', $result->postingResult()->journalEntry()?->lines()[2]->credit()?->amount());
        self::assertSame('200', $posting->taxableBase()->amount());
        self::assertSame('18', $posting->taxAmount()->amount());
        self::assertSame('9', $posting->taxRate()->value());
    }

    public function test_zero_percent_creates_no_tax_line_but_retains_fiscal_truth(): void
    {
        $invoice = $this->invoiceWithLines([['1', '100']]);
        $input = $this->input($invoice->lines()[0], '0', includeTaxLine: false);

        $result = $this->execute($invoice, [$input]);
        $entry = $result->postingResult()->journalEntry();
        self::assertNotNull($entry);

        self::assertCount(2, $entry->lines());
        self::assertSame('100', $entry->line($input->expenseLineId())?->debit()?->amount());
        self::assertSame('100', $entry->lines()[1]->credit()?->amount());
        self::assertNull($input->taxLineId());
        self::assertTrue($result->taxPostings()[0]->taxAmount()->isZero());
        self::assertSame('0', $result->taxPostings()[0]->taxRate()->value());
        self::assertNull($result->taxPostings()[0]->taxJournalEntryLineId());
        self::assertSame($input->expenseLineId(), $result->taxPostings()[0]->baseJournalEntryLineId());
    }

    public function test_multiple_lines_with_same_rate_create_individual_expense_tax_and_trace_lines(): void
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
        self::assertSame('181.5', $entry->lines()[4]->credit()?->amount());
        self::assertSame(['100', '50'], array_map(
            static fn (PurchasingFiscalLineInput $input): ?string => $entry->line($input->expenseLineId())?->debit()?->amount(),
            $inputs,
        ));
        self::assertSame(['21', '10.5'], array_map(
            static fn (PurchasingFiscalLineInput $input): ?string => $entry->line($input->taxLineId())?->debit()?->amount(),
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

        self::assertSame('230', $result->postingResult()->journalEntry()?->lines()[4]->credit()?->amount());
        self::assertSame(['21', '9'], array_map(
            static fn ($posting): string => $posting->taxRate()->value(),
            $result->taxPostings(),
        ));
        self::assertSame(
            array_map(static fn (PurchasingFiscalLineInput $input): string => $input->taxCode()->id()->toString(), $inputs),
            array_map(static fn ($posting): string => $posting->taxCodeId()->toString(), $result->taxPostings()),
        );
    }

    #[DataProvider('rejectedStatuses')]
    public function test_draft_and_cancelled_invoices_are_rejected(PurchaseInvoiceStatus $status): void
    {
        $invoice = $this->invoiceWithLines([['1', '100']], finalize: false);

        if ($status === PurchaseInvoiceStatus::Cancelled) {
            $invoice->cancel();
        }

        $this->expectException(DomainException::class);
        $this->execute($invoice, [$this->input($invoice->lines()[0], '21')]);
    }

    /** @return array<string, array{PurchaseInvoiceStatus}> */
    public static function rejectedStatuses(): array
    {
        return [
            'draft' => [PurchaseInvoiceStatus::Draft],
            'cancelled' => [PurchaseInvoiceStatus::Cancelled],
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
        $input = $this->input($invoice->lines()[0], '21', expenseLineId: $duplicateId);

        $result = $this->execute($invoice, [$input], creditorLineId: $duplicateId);

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

    /** @param list<PurchasingFiscalLineInput> $inputs */
    private function execute(
        PurchaseInvoice $invoice,
        array $inputs,
        ?JournalEntryLineId $creditorLineId = null,
    ): PurchasingFiscalPostingResult {
        $validation = new PostingValidation;
        $useCase = new PostPurchaseInvoiceWithTax(
            new TaxCalculation,
            new TaxPostingIdentityPolicy,
            new PostingEngine(
                $validation,
                static fn (): JournalEntryId => new JournalEntryId(new Uuid(self::POSTED_ENTRY_ID)),
            ),
        );

        return $useCase->execute(
            $invoice,
            $inputs,
            [],
            new JournalId($this->nextUuid('6')),
            new LedgerAccountId($this->nextUuid('7')),
            $creditorLineId ?? $this->journalEntryLineId(),
            new PostingDate(new DateTimeImmutable('2026-08-19')),
            new JournalEntryReference('PINV-FISCAL-001'),
        );
    }

    /** @param list<array{string, string}> $lines */
    private function invoiceWithLines(array $lines, bool $finalize = true): PurchaseInvoice
    {
        $invoice = new PurchaseInvoice(
            new PurchaseInvoiceId($this->nextUuid('1')),
            new PurchaseInvoiceNumber('pinv-fiscal-001'),
            new AdministrationId($this->nextUuid('2')),
            new SupplierId($this->nextUuid('3')),
            new Currency('EUR'),
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-31'),
            null,
            PurchaseInvoiceStatus::Draft,
        );

        foreach ($lines as [$quantity, $unitPrice]) {
            $invoice->addLine(new PurchaseInvoiceLine(
                new PurchaseInvoiceLineId($this->nextUuid('4')),
                new LineDescription('Fiscal purchase line'),
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
        PurchaseInvoiceLine $line,
        string $rate,
        bool $includeTaxLine = true,
        ?JournalEntryLineId $expenseLineId = null,
        ?TaxPostingId $taxPostingId = null,
    ): PurchasingFiscalLineInput {
        return new PurchasingFiscalLineInput(
            $line->id(),
            new TaxCode(
                new TaxCodeId($this->nextUuid('5')),
                new TaxCodeCode('vat'.$rate),
                new TaxCodeName('VAT '.$rate.'%'),
                new TaxRate($rate),
                TaxPostingDirection::Input,
                TaxCodeStatus::Active,
            ),
            new LedgerAccountId($this->nextUuid('7')),
            new LedgerAccountId($this->nextUuid('7')),
            $expenseLineId ?? $this->journalEntryLineId(),
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
