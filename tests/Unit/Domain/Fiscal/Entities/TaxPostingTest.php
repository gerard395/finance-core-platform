<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\Entities;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TaxPostingTest extends TestCase
{
    public function test_valid_output_tax_posting_exposes_complete_immutable_trace(): void
    {
        $id = $this->taxPostingId(1);
        $administrationId = $this->administrationId();
        $taxCodeId = $this->taxCodeId();
        $taxRate = new TaxRate('21');
        $currency = new Currency('EUR');
        $taxableBase = new Money('100', $currency);
        $taxAmount = new Money('21', $currency);
        $sourceDocumentId = $this->sourceDocumentId();
        $sourceLineId = $this->sourceLineId();
        $postingDate = new PostingDate(new DateTimeImmutable('2026-08-19'));
        $journalEntryId = $this->journalEntryId();
        $baseLineId = $this->journalEntryLineId(1);
        $taxLineId = $this->journalEntryLineId(2);

        $posting = new TaxPosting(
            $id,
            $administrationId,
            $taxCodeId,
            $taxRate,
            $taxableBase,
            $taxAmount,
            TaxPostingDirection::Output,
            TaxSourceDocumentType::SalesInvoice,
            $sourceDocumentId,
            $sourceLineId,
            $postingDate,
            $journalEntryId,
            $baseLineId,
            $taxLineId,
            TaxPostingType::Original,
        );

        self::assertTrue((new ReflectionClass(TaxPosting::class))->isReadOnly());
        self::assertSame($id, $posting->id());
        self::assertSame($administrationId, $posting->administrationId());
        self::assertSame($taxCodeId, $posting->taxCodeId());
        self::assertSame($taxRate, $posting->taxRate());
        self::assertSame($taxableBase, $posting->taxableBase());
        self::assertSame($taxAmount, $posting->taxAmount());
        self::assertSame(TaxPostingDirection::Output, $posting->direction());
        self::assertSame(TaxSourceDocumentType::SalesInvoice, $posting->sourceDocumentType());
        self::assertSame($sourceDocumentId, $posting->sourceDocumentId());
        self::assertSame($sourceLineId, $posting->sourceLineId());
        self::assertSame($postingDate, $posting->postingDate());
        self::assertSame($journalEntryId, $posting->journalEntryId());
        self::assertSame($baseLineId, $posting->baseJournalEntryLineId());
        self::assertSame($taxLineId, $posting->taxJournalEntryLineId());
        self::assertSame(TaxPostingType::Original, $posting->type());
        self::assertNull($posting->reversedTaxPostingId());
        self::assertFalse($posting->isReversal());
    }

    public function test_valid_input_tax_posting_and_zero_amounts_are_supported(): void
    {
        $posting = $this->posting(
            direction: TaxPostingDirection::Input,
            sourceDocumentType: TaxSourceDocumentType::PurchaseInvoice,
            taxableBase: '0',
            taxAmount: '0',
            includeTaxLine: false,
        );

        self::assertSame(TaxPostingDirection::Input, $posting->direction());
        self::assertSame(TaxSourceDocumentType::PurchaseInvoice, $posting->sourceDocumentType());
        self::assertTrue($posting->taxableBase()->isZero());
        self::assertTrue($posting->taxAmount()->isZero());
        self::assertNull($posting->taxJournalEntryLineId());
    }

    public function test_positive_tax_amount_without_tax_line_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->posting(includeTaxLine: false);
    }

    public function test_zero_tax_amount_with_tax_line_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->posting(taxAmount: '0');
    }

    public function test_zero_tax_output_posting_has_no_artificial_tax_line(): void
    {
        $posting = $this->posting(
            direction: TaxPostingDirection::Output,
            sourceDocumentType: TaxSourceDocumentType::SalesInvoice,
            taxAmount: '0',
            taxRate: new TaxRate('0'),
            includeTaxLine: false,
        );

        self::assertSame(TaxPostingDirection::Output, $posting->direction());
        self::assertSame(TaxSourceDocumentType::SalesInvoice, $posting->sourceDocumentType());
        self::assertSame('0', $posting->taxRate()->value());
        self::assertNull($posting->taxJournalEntryLineId());
        self::assertNotNull($posting->baseJournalEntryLineId());
    }

    public function test_zero_tax_input_posting_has_no_artificial_tax_line(): void
    {
        $posting = $this->posting(
            direction: TaxPostingDirection::Input,
            sourceDocumentType: TaxSourceDocumentType::PurchaseInvoice,
            taxAmount: '0',
            taxRate: new TaxRate('0'),
            includeTaxLine: false,
        );

        self::assertSame(TaxPostingDirection::Input, $posting->direction());
        self::assertSame(TaxSourceDocumentType::PurchaseInvoice, $posting->sourceDocumentType());
        self::assertNull($posting->taxJournalEntryLineId());
    }

    public function test_tax_rate_is_retained_as_transaction_snapshot(): void
    {
        $rate = new TaxRate('9.25');
        $posting = $this->posting(taxRate: $rate);

        self::assertSame($rate, $posting->taxRate());
        self::assertSame('9.25', $posting->taxRate()->value());
    }

    public function test_different_currencies_are_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->posting(taxAmountCurrency: new Currency('USD'));
    }

    public function test_negative_taxable_base_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->posting(taxableBase: '-0.01');
    }

    public function test_negative_tax_amount_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->posting(taxAmount: '-0.01');
    }

    public function test_optional_reversal_relation_does_not_mutate_original_posting(): void
    {
        $original = $this->posting(id: $this->taxPostingId(1));
        $originalRate = $original->taxRate();
        $originalBase = $original->taxableBase();
        $originalTax = $original->taxAmount();

        $reversal = $this->posting(
            id: $this->taxPostingId(2),
            type: TaxPostingType::Reversal,
            reversedTaxPostingId: $original->id(),
        );

        self::assertTrue($reversal->isReversal());
        self::assertSame(TaxPostingType::Reversal, $reversal->type());
        self::assertSame($original->id(), $reversal->reversedTaxPostingId());
        self::assertNull($original->reversedTaxPostingId());
        self::assertFalse($original->isReversal());
        self::assertSame($originalRate, $original->taxRate());
        self::assertSame($originalBase, $original->taxableBase());
        self::assertSame($originalTax, $original->taxAmount());
    }

    public function test_original_with_reversal_reference_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->posting(reversedTaxPostingId: $this->taxPostingId(2));
    }

    public function test_reversal_without_original_reference_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->posting(type: TaxPostingType::Reversal);
    }

    public function test_zero_percent_reversal_has_positive_base_and_no_tax_line(): void
    {
        $reversal = $this->posting(
            taxableBase: '100',
            taxAmount: '0',
            taxRate: new TaxRate('0'),
            type: TaxPostingType::Reversal,
            reversedTaxPostingId: $this->taxPostingId(2),
            includeTaxLine: false,
        );

        self::assertTrue($reversal->taxableBase()->isPositive());
        self::assertTrue($reversal->taxAmount()->isZero());
        self::assertNull($reversal->taxJournalEntryLineId());
    }

    public function test_credit_document_types_are_available(): void
    {
        self::assertSame('sales_credit_invoice', TaxSourceDocumentType::SalesCreditInvoice->value);
        self::assertSame('purchase_credit_invoice', TaxSourceDocumentType::PurchaseCreditInvoice->value);
    }

    public function test_money_values_remain_exact_decimal_strings_without_floats(): void
    {
        $posting = $this->posting(taxableBase: '0.3', taxAmount: '0.12345678');

        self::assertSame('0.3', $posting->taxableBase()->amount());
        self::assertSame('0.12345678', $posting->taxAmount()->amount());
        self::assertIsString($posting->taxableBase()->amount());
        self::assertIsString($posting->taxAmount()->amount());
    }

    private function posting(
        ?TaxPostingId $id = null,
        TaxPostingDirection $direction = TaxPostingDirection::Output,
        TaxSourceDocumentType $sourceDocumentType = TaxSourceDocumentType::SalesInvoice,
        string $taxableBase = '100',
        string $taxAmount = '21',
        ?TaxRate $taxRate = null,
        ?Currency $taxAmountCurrency = null,
        TaxPostingType $type = TaxPostingType::Original,
        ?TaxPostingId $reversedTaxPostingId = null,
        bool $includeTaxLine = true,
    ): TaxPosting {
        $currency = new Currency('EUR');

        return new TaxPosting(
            $id ?? $this->taxPostingId(1),
            $this->administrationId(),
            $this->taxCodeId(),
            $taxRate ?? new TaxRate('21'),
            new Money($taxableBase, $currency),
            new Money($taxAmount, $taxAmountCurrency ?? $currency),
            $direction,
            $sourceDocumentType,
            $this->sourceDocumentId(),
            $this->sourceLineId(),
            new PostingDate(new DateTimeImmutable('2026-08-19')),
            $this->journalEntryId(),
            $this->journalEntryLineId(1),
            $includeTaxLine ? $this->journalEntryLineId(2) : null,
            $type,
            $reversedTaxPostingId,
        );
    }

    private function taxPostingId(int $suffix): TaxPostingId
    {
        return new TaxPostingId($this->uuid('1', $suffix));
    }

    private function administrationId(): AdministrationId
    {
        return new AdministrationId($this->uuid('2', 1));
    }

    private function taxCodeId(): TaxCodeId
    {
        return new TaxCodeId($this->uuid('3', 1));
    }

    private function sourceDocumentId(): TaxSourceDocumentId
    {
        return new TaxSourceDocumentId($this->uuid('4', 1));
    }

    private function sourceLineId(): TaxSourceLineId
    {
        return new TaxSourceLineId($this->uuid('5', 1));
    }

    private function journalEntryId(): JournalEntryId
    {
        return new JournalEntryId($this->uuid('6', 1));
    }

    private function journalEntryLineId(int $suffix): JournalEntryLineId
    {
        return new JournalEntryLineId($this->uuid('7', $suffix));
    }

    private function uuid(string $prefix, int $suffix): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $suffix));
    }
}
