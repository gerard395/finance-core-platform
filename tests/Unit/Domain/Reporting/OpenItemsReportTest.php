<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reporting;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Reporting\OpenItemsReport;
use App\Domain\Reporting\OpenItemsResult;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class OpenItemsReportTest extends TestCase
{
    private const string ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000001';

    private const string OTHER_ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000002';

    private const string RELATION_ID = '20000000-0000-4000-8000-000000000001';

    private const string OTHER_RELATION_ID = '20000000-0000-4000-8000-000000000002';

    private int $identitySequence = 1;

    public function test_empty_dataset_returns_explicit_context_zero_totals_and_counts(): void
    {
        $administrationId = $this->administrationId(self::ADMINISTRATION_ID);
        $currency = new Currency('EUR');
        $asOfDate = $this->date('2026-01-31');

        $result = $this->generate([], $administrationId, $currency, $asOfDate);

        self::assertSame($administrationId, $result->administrationId());
        self::assertSame($currency, $result->currency());
        self::assertSame($asOfDate, $result->asOfDate());
        self::assertSame([], $result->lines());
        self::assertTrue($result->totalOriginalAmount()->isZero());
        self::assertTrue($result->totalOpenAmount()->isZero());
        self::assertSame(0, $result->countOpen());
        self::assertSame(0, $result->countPartiallySettled());
        self::assertSame(0, $result->countClosed());
    }

    public function test_fully_open_item_exposes_source_data_and_is_totalled(): void
    {
        $item = $this->openItem('100', '2026-01-01');

        $result = $this->generate([$item]);
        $line = $result->lines()[0];

        self::assertSame($item->id(), $line->openItemId());
        self::assertSame($item->relationId(), $line->relationId());
        self::assertSame($item->journalEntryId(), $line->journalEntryId());
        self::assertSame($item->openedOn(), $line->openedOn());
        self::assertSame($item->originalAmount(), $line->originalAmount());
        self::assertSame('100', $line->openAmount()->amount());
        self::assertSame(OpenItemStatus::Open, $line->status());
        self::assertSame('100', $result->totalOriginalAmount()->amount());
        self::assertSame('100', $result->totalOpenAmount()->amount());
        self::assertSame(1, $result->countOpen());
    }

    public function test_historical_state_is_open_before_and_partial_after_first_settlement(): void
    {
        $item = $this->openItem('1000', '2026-01-01');
        $item->applySettlement(
            $this->settlementId(),
            $this->date('2026-01-15'),
            $this->money('400'),
            $this->journalEntryId(),
        );

        $before = $this->generate([$item], asOfDate: $this->date('2026-01-10'))->lines()[0];
        $after = $this->generate([$item], asOfDate: $this->date('2026-01-20'))->lines()[0];

        self::assertSame('1000', $before->openAmount()->amount());
        self::assertSame(OpenItemStatus::Open, $before->status());
        self::assertSame('600', $after->openAmount()->amount());
        self::assertSame(OpenItemStatus::PartiallySettled, $after->status());
    }

    public function test_closed_item_is_excluded_by_default_and_included_explicitly(): void
    {
        $item = $this->openItem('1000', '2026-01-01');
        $item->applySettlement(
            $this->settlementId(),
            $this->date('2026-01-15'),
            $this->money('1000'),
            $this->journalEntryId(),
        );

        $default = $this->generate([$item], asOfDate: $this->date('2026-01-31'));
        $included = $this->generate([$item], asOfDate: $this->date('2026-01-31'), includeClosed: true);

        self::assertSame([], $default->lines());
        self::assertTrue($default->totalOriginalAmount()->isZero());
        self::assertSame(1, $included->countClosed());
        self::assertSame(OpenItemStatus::Closed, $included->lines()[0]->status());
        self::assertTrue($included->lines()[0]->openAmount()->isZero());
        self::assertSame('1000', $included->totalOriginalAmount()->amount());
        self::assertTrue($included->totalOpenAmount()->isZero());
    }

    public function test_other_administration_and_future_open_item_are_ignored(): void
    {
        $otherAdministration = $this->openItem('50', '2026-01-01', self::OTHER_ADMINISTRATION_ID);
        $future = $this->openItem('75', '2026-02-01');

        $result = $this->generate([$otherAdministration, $future], asOfDate: $this->date('2026-01-31'));

        self::assertSame([], $result->lines());
        self::assertTrue($result->totalOpenAmount()->isZero());
    }

    public function test_optional_relation_filter_selects_only_requested_relation(): void
    {
        $requested = $this->openItem('10', '2026-01-01', relationId: self::RELATION_ID);
        $other = $this->openItem('20', '2026-01-01', relationId: self::OTHER_RELATION_ID);

        $result = $this->generate(
            [$other, $requested],
            relationId: $this->relationId(self::RELATION_ID),
        );

        self::assertCount(1, $result->lines());
        self::assertSame($requested->id(), $result->lines()[0]->openItemId());
        self::assertSame('10', $result->totalOriginalAmount()->amount());
    }

    public function test_totals_and_status_counts_include_only_report_lines(): void
    {
        $open = $this->openItem('100.12345678', '2026-01-01');
        $partial = $this->openItem('50', '2026-01-02');
        $partial->applySettlement(
            $this->settlementId(),
            $this->date('2026-01-10'),
            $this->money('20.02345678'),
            $this->journalEntryId(),
        );
        $closed = $this->openItem('25', '2026-01-03');
        $closed->applySettlement(
            $this->settlementId(),
            $this->date('2026-01-10'),
            $this->money('25'),
            $this->journalEntryId(),
        );

        $result = $this->generate([$closed, $partial, $open], includeClosed: true);

        self::assertSame('175.12345678', $result->totalOriginalAmount()->amount());
        self::assertSame('130.1', $result->totalOpenAmount()->amount());
        self::assertSame(1, $result->countOpen());
        self::assertSame(1, $result->countPartiallySettled());
        self::assertSame(1, $result->countClosed());
    }

    public function test_lines_are_sorted_by_opening_date_then_open_item_id(): void
    {
        $laterDateSmallId = $this->openItem('10', '2026-01-02', openItemId: '30000000-0000-4000-8000-000000000001');
        $sameDateLargeId = $this->openItem('10', '2026-01-01', openItemId: '30000000-0000-4000-8000-000000000003');
        $sameDateSmallId = $this->openItem('10', '2026-01-01', openItemId: '30000000-0000-4000-8000-000000000002');

        $result = $this->generate([$laterDateSmallId, $sameDateLargeId, $sameDateSmallId]);

        self::assertSame(
            [
                '30000000-0000-4000-8000-000000000002',
                '30000000-0000-4000-8000-000000000003',
                '30000000-0000-4000-8000-000000000001',
            ],
            array_map(static fn ($line): string => $line->openItemId()->toString(), $result->lines()),
        );
    }

    public function test_report_context_currency_is_preserved_and_mismatch_is_rejected(): void
    {
        $currency = new Currency('EUR');
        $result = $this->generate([$this->openItem('10', '2026-01-01')], currency: $currency);
        self::assertSame($currency, $result->currency());

        $this->expectException(DomainException::class);
        $this->generate([$this->openItem('10', '2026-01-01', currency: new Currency('USD'))], currency: $currency);
    }

    public function test_reporting_does_not_mutate_open_item_or_settlement_history(): void
    {
        $item = $this->openItem('100', '2026-01-01');
        $item->applySettlement(
            $this->settlementId(),
            $this->date('2026-01-15'),
            $this->money('40'),
            $this->journalEntryId(),
        );
        $settlements = $item->settlements();
        $openAmount = $item->openAmount();
        $status = $item->status();

        $this->generate([$item], asOfDate: $this->date('2026-01-20'));

        self::assertSame($settlements, $item->settlements());
        self::assertTrue($openAmount->equals($item->openAmount()));
        self::assertSame($status, $item->status());
    }

    public function test_output_money_uses_exact_decimal_strings_without_floats(): void
    {
        $item = $this->openItem('0.3', '2026-01-01');
        $item->applySettlement(
            $this->settlementId(),
            $this->date('2026-01-15'),
            $this->money('0.1'),
            $this->journalEntryId(),
        );

        $result = $this->generate([$item]);

        self::assertSame('0.2', $result->lines()[0]->openAmount()->amount());
        self::assertIsString($result->lines()[0]->originalAmount()->amount());
        self::assertIsString($result->lines()[0]->openAmount()->amount());
        self::assertIsString($result->totalOriginalAmount()->amount());
        self::assertIsString($result->totalOpenAmount()->amount());
    }

    public function test_receivable_debit_and_credit_are_reported_gross_and_net_without_payable_reclassification(): void
    {
        $debit = $this->openItem('121', '2026-01-01');
        $credit = new OpenItem(
            new OpenItemId($this->nextUuid('3')),
            $this->administrationId(self::ADMINISTRATION_ID),
            $this->relationId(self::RELATION_ID),
            $this->journalEntryId(),
            OpenItemType::Receivable,
            $this->money('40'),
            $this->date('2026-01-02'),
            OpenItemSide::Credit,
        );

        $result = $this->generate([$debit, $credit]);

        self::assertSame(OpenItemType::Receivable, $result->lines()[1]->type());
        self::assertSame(OpenItemSide::Credit, $result->lines()[1]->side());
        self::assertSame('121', $result->totalDebitOpenAmount()->amount());
        self::assertSame('40', $result->totalCreditOpenAmount()->amount());
        self::assertSame('81', $result->netReceivableOpenAmount()->amount());
        self::assertSame('161', $result->totalOpenAmount()->amount());
    }

    /** @param list<OpenItem> $openItems */
    private function generate(
        array $openItems,
        ?AdministrationId $administrationId = null,
        ?Currency $currency = null,
        ?PostingDate $asOfDate = null,
        ?RelationId $relationId = null,
        bool $includeClosed = false,
    ): OpenItemsResult {
        return (new OpenItemsReport)->generate(
            $openItems,
            $administrationId ?? $this->administrationId(self::ADMINISTRATION_ID),
            $currency ?? new Currency('EUR'),
            $asOfDate ?? $this->date('2026-01-31'),
            $relationId,
            $includeClosed,
        );
    }

    private function openItem(
        string $amount,
        string $openedOn,
        string $administrationId = self::ADMINISTRATION_ID,
        string $relationId = self::RELATION_ID,
        ?string $openItemId = null,
        ?Currency $currency = null,
    ): OpenItem {
        return new OpenItem(
            new OpenItemId($openItemId === null ? $this->nextUuid('3') : new Uuid($openItemId)),
            $this->administrationId($administrationId),
            $this->relationId($relationId),
            $this->journalEntryId(),
            OpenItemType::Receivable,
            new Money($amount, $currency ?? new Currency('EUR')),
            $this->date($openedOn),
        );
    }

    private function administrationId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function relationId(string $id): RelationId
    {
        return new RelationId(new Uuid($id));
    }

    private function journalEntryId(): JournalEntryId
    {
        return new JournalEntryId($this->nextUuid('4'));
    }

    private function settlementId(): OpenItemSettlementId
    {
        return new OpenItemSettlementId($this->nextUuid('5'));
    }

    private function date(string $date): PostingDate
    {
        return new PostingDate(new DateTimeImmutable($date));
    }

    private function money(string $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }

    private function nextUuid(string $prefix): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $this->identitySequence++));
    }
}
