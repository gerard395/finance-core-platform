<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\PostSalesCreditInvoiceWithTax;
use App\Application\Sales\SalesCreditFiscalLineInput;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\Services\TaxPostingReversalPolicy;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesCreditInvoiceLine;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostSalesCreditInvoiceWithTaxTest extends TestCase
{
    private int $sequence = 1;

    #[DataProvider('rates')]
    public function test_full_reversal_posts_and_traces_exact_snapshot(string $rate, string $tax, bool $taxLine): void
    {
        $invoice = $this->creditInvoice([['100']]);
        $original = $this->original('100', $tax, $rate);
        $input = $this->input($invoice->lines()[0], $original, $taxLine);
        $history = [$original];

        $result = $this->execute($invoice, [$input], $history);
        $entry = $result->postingResult()->journalEntry();
        self::assertNotNull($entry);
        self::assertTrue((new PostingValidation)->validate($result->postingRequest())->isValid());
        self::assertSame((new Money('100', new Currency('EUR')))->add(new Money($tax, new Currency('EUR')))->amount(), $entry->lines()[count($entry->lines()) - 1]->credit()?->amount());
        self::assertSame('100', $entry->line($input->revenueReversalLineId())?->debit()?->amount());
        self::assertSame($taxLine ? $tax : null, $input->taxReversalLineId() === null ? null : $entry->line($input->taxReversalLineId())?->debit()?->amount());
        $reversal = $result->taxPostings()[0];
        self::assertSame(TaxPostingType::Reversal, $reversal->type());
        self::assertSame(TaxPostingDirection::Output, $reversal->direction());
        self::assertSame(TaxSourceDocumentType::SalesCreditInvoice, $reversal->sourceDocumentType());
        self::assertTrue($reversal->reversedTaxPostingId()?->equals($original->id()));
        self::assertTrue($reversal->taxCodeId()->equals($original->taxCodeId()));
        self::assertTrue($reversal->taxRate()->equals($original->taxRate()));
        self::assertTrue($reversal->taxableBase()->equals($original->taxableBase()));
        self::assertTrue($reversal->taxAmount()->equals($original->taxAmount()));
        self::assertSame($invoice->id()->toString(), $reversal->sourceDocumentId()->toString());
        self::assertSame($invoice->lines()[0]->id()->toString(), $reversal->sourceLineId()->toString());
        self::assertSame($entry->id(), $reversal->journalEntryId());
        self::assertSame($input->revenueReversalLineId(), $reversal->baseJournalEntryLineId());
        self::assertSame($input->taxReversalLineId(), $reversal->taxJournalEntryLineId());
        self::assertSame([$original], $history);
        self::assertSame(TaxPostingType::Original, $original->type());
    }

    public static function rates(): array
    {
        return [['21', '21', true], ['9', '9', true], ['0', '0', false]];
    }

    public function test_multiple_tax_codes_and_same_request_duplicate_are_handled(): void
    {
        $invoice = $this->creditInvoice([['100'], ['50']]);
        $first = $this->original('100', '21', '21');
        $second = $this->original('50', '4.5', '9');
        $result = $this->execute($invoice, [$this->input($invoice->lines()[0], $first), $this->input($invoice->lines()[1], $second)], [$first, $second]);
        self::assertCount(2, $result->taxPostings());
        self::assertSame('175.5', $result->postingResult()->journalEntry()?->lines()[4]->credit()?->amount());

        $duplicateInvoice = $this->creditInvoice([['100'], ['100']]);
        $this->expectException(DomainException::class);
        $this->execute($duplicateInvoice, [$this->input($duplicateInvoice->lines()[0], $first), $this->input($duplicateInvoice->lines()[1], $first)], [$first]);
    }

    public function test_history_and_target_guards_reject_invalid_inputs(): void
    {
        $invoice = $this->creditInvoice([['100']]);
        $original = $this->original('100', '21', '21');
        $existing = $this->reversal($original);
        $this->expectException(DomainException::class);
        $this->execute($invoice, [$this->input($invoice->lines()[0], $original)], [$original, $existing]);
    }

    public function test_input_target_and_context_are_validated(): void
    {
        $invoice = $this->creditInvoice([['100']]);
        $input = $this->original('100', '21', '21', TaxPostingDirection::Input);
        $this->expectException(DomainException::class);
        $this->execute($invoice, [$this->input($invoice->lines()[0], $input)], [$input]);
    }

    public function test_draft_is_rejected_and_failed_posting_returns_no_reversals(): void
    {
        $draft = $this->creditInvoice([['100']], false);
        $original = $this->original('100', '21', '21');
        try {
            $this->execute($draft, [$this->input($draft->lines()[0], $original)], [$original]);
            self::fail();
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $invoice = $this->creditInvoice([['100']]);
        $duplicate = $this->lineId();
        $input = $this->input($invoice->lines()[0], $original, true, $duplicate);
        $result = $this->execute($invoice, [$input], [$original], $duplicate);
        self::assertFalse($result->postingResult()->isSuccess());
        self::assertSame([], $result->taxPostings());
    }

    private function execute(SalesCreditInvoice $invoice, array $inputs, array $history, ?JournalEntryLineId $debtorLine = null)
    {
        return (new PostSalesCreditInvoiceWithTax(new TaxPostingReversalPolicy, new PostingEngine(new PostingValidation, fn () => new JournalEntryId($this->uuid('a')))))->execute(
            $invoice, $inputs, $history, new JournalId($this->uuid('b')), new LedgerAccountId($this->uuid('c')), $debtorLine ?? $this->lineId(), new PostingDate(new DateTimeImmutable('2026-08-19')), new JournalEntryReference('SC-FISCAL-1')
        );
    }

    private function creditInvoice(array $amounts, bool $finalize = true): SalesCreditInvoice
    {
        $invoice = new SalesCreditInvoice(new SalesCreditInvoiceId($this->uuid('1')), new SalesCreditInvoiceNumber('sc-1'), new AdministrationId(new Uuid('20000000-0000-4000-8000-000000000001')), new CustomerId($this->uuid('3')), new Currency('EUR'), new DateTimeImmutable('2026-08-19'), new SalesInvoiceId($this->uuid('4')), SalesCreditInvoiceStatus::Draft);
        foreach ($amounts as [$amount]) {
            $invoice->addLine(new SalesCreditInvoiceLine(new SalesCreditInvoiceLineId($this->uuid('5')), new LineDescription('credit'), new Quantity('1'), new Money($amount, new Currency('EUR'))));
        }
        if ($finalize) {
            $invoice->finalize();
        }

        return $invoice;
    }

    private function original(string $base, string $tax, string $rate, TaxPostingDirection $direction = TaxPostingDirection::Output): TaxPosting
    {
        return new TaxPosting(new TaxPostingId($this->uuid('6')), new AdministrationId(new Uuid('20000000-0000-4000-8000-000000000001')), new TaxCodeId($this->uuid('7')), new TaxRate($rate), new Money($base, new Currency('EUR')), new Money($tax, new Currency('EUR')), $direction, TaxSourceDocumentType::SalesInvoice, new TaxSourceDocumentId($this->uuid('8')), new TaxSourceLineId($this->uuid('9')), new PostingDate(new DateTimeImmutable('2026-08-01')), new JournalEntryId($this->uuid('d')), $this->lineId(), $tax === '0' ? null : $this->lineId(), TaxPostingType::Original);
    }

    private function reversal(TaxPosting $original): TaxPosting
    {
        return new TaxPosting(new TaxPostingId($this->uuid('6')), $original->administrationId(), $original->taxCodeId(), $original->taxRate(), $original->taxableBase(), $original->taxAmount(), $original->direction(), TaxSourceDocumentType::SalesCreditInvoice, new TaxSourceDocumentId($this->uuid('8')), new TaxSourceLineId($this->uuid('9')), new PostingDate(new DateTimeImmutable('2026-08-19')), new JournalEntryId($this->uuid('d')), $this->lineId(), $original->taxAmount()->isZero() ? null : $this->lineId(), TaxPostingType::Reversal, $original->id());
    }

    private function input(SalesCreditInvoiceLine $line, TaxPosting $original, bool $taxLine = true, ?JournalEntryLineId $revenue = null): SalesCreditFiscalLineInput
    {
        return new SalesCreditFiscalLineInput($line->id(), $original, new LedgerAccountId($this->uuid('c')), new LedgerAccountId($this->uuid('c')), $revenue ?? $this->lineId(), $taxLine ? $this->lineId() : null, new TaxPostingId($this->uuid('6')));
    }

    private function lineId(): JournalEntryLineId
    {
        return new JournalEntryLineId($this->uuid('e'));
    }

    private function uuid(string $prefix): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $this->sequence++));
    }
}
