<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reporting;

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
use App\Domain\Reporting\VatOverview;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class VatOverviewTest extends TestCase
{
    private int $n = 1;

    public function test_empty_result_and_context(): void
    {
        $r = $this->report([]);
        self::assertSame([], $r->lines());
        self::assertSame('0', $r->netVatPosition()->amount());
        self::assertSame('EUR', $r->currency()->code());
    }

    public function test_originals_reversals_directions_zero_and_grouping(): void
    {
        $outputCode = new TaxCodeId($this->uuid('7'));
        $output = $this->posting('2026-01-15', '1000', '210', '21', TaxPostingDirection::Output, taxCode: $outputCode);
        $input = $this->posting('2026-01-16', '500', '105', '21', TaxPostingDirection::Input);
        $nine = $this->posting('2026-01-17', '100', '9', '9', TaxPostingDirection::Output);
        $zero = $this->posting('2026-01-18', '50', '0', '0', TaxPostingDirection::Output);
        $reversal = $this->posting('2026-02-10', '1000', '210', '21', TaxPostingDirection::Output, TaxPostingType::Reversal, $output->id(), $outputCode);
        $r = $this->report([$reversal, $zero, $nine, $input, $output]);
        self::assertCount(5, $r->lines());
        self::assertSame('150', $r->totalOutputTaxableBase()->amount());
        self::assertSame('9', $r->totalOutputTax()->amount());
        self::assertSame('500', $r->totalInputTaxableBase()->amount());
        self::assertSame('105', $r->totalInputTax()->amount());
        self::assertSame('-96', $r->netVatPosition()->amount());
        self::assertCount(4, $r->taxCodeSummaries());
        self::assertTrue($r->lines()[0]->id()->equals($output->id()));
        self::assertNull($r->lines()[3]->taxJournalEntryLineId());
        self::assertTrue($r->lines()[4]->reversedTaxPostingId()->equals($output->id()));
        self::assertSame(TaxPostingType::Original, $output->type());
    }

    public function test_rate_snapshots_form_separate_groups(): void
    {
        $id = new TaxCodeId($this->uuid('7'));
        $r = $this->report([$this->posting('2026-01-01', '100', '21', '21', taxCode: $id), $this->posting('2026-01-02', '100', '9', '9', taxCode: $id)]);
        self::assertCount(2, $r->taxCodeSummaries());
    }

    public function test_filters_and_inclusive_boundaries(): void
    {
        $r = $this->report([$this->posting('2025-12-31', '1', '0.21', '21'), $this->posting('2026-01-01', '2', '0.42', '21'), $this->posting('2026-02-28', '3', '0.63', '21'), $this->posting('2026-03-01', '4', '0.84', '21')]);
        self::assertCount(2, $r->lines());
    }

    public function test_other_administration_is_ignored(): void
    {
        $other = new AdministrationId($this->uuid('8'));
        self::assertSame([], $this->report([$this->posting('2026-01-01', '1', '0.21', '21', administration: $other)])->lines());
    }

    public function test_selected_currency_mismatch_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->report([$this->posting('2026-01-01', '1', '0.21', '21', currency: 'USD')]);
    }

    private function report(array $p)
    {
        return (new VatOverview)->calculate($p, $this->admin(), new Currency('EUR'), new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-28'));
    }

    private function posting(string $date, string $base, string $tax, string $rate, TaxPostingDirection $direction = TaxPostingDirection::Output, TaxPostingType $type = TaxPostingType::Original, ?TaxPostingId $reversed = null, ?TaxCodeId $taxCode = null, ?AdministrationId $administration = null, string $currency = 'EUR'): TaxPosting
    {
        $c = new Currency($currency);

        return new TaxPosting(new TaxPostingId($this->uuid('1')), $administration ?? $this->admin(), $taxCode ?? new TaxCodeId($this->uuid('7')), new TaxRate($rate), new Money($base, $c), new Money($tax, $c), $direction, TaxSourceDocumentType::SalesInvoice, new TaxSourceDocumentId($this->uuid('2')), new TaxSourceLineId($this->uuid('3')), new PostingDate(new DateTimeImmutable($date)), new JournalEntryId($this->uuid('4')), new JournalEntryLineId($this->uuid('5')), $tax === '0' ? null : new JournalEntryLineId($this->uuid('6')), $type, $reversed);
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid('90000000-0000-4000-8000-000000000001'));
    }

    private function uuid(string $p): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $p, $this->n++));
    }
}
