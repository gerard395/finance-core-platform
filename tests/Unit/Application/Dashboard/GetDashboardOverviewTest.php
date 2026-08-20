<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Dashboard;

use App\Application\Accounting\JournalEntryReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Dashboard\DashboardOverview;
use App\Application\Dashboard\GetDashboardOverview;
use App\Application\Fiscal\TaxPostingReadRepository;
use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
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
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Reporting\OpenItemsReport;
use App\Domain\Reporting\ProfitAndLoss;
use App\Domain\Reporting\TrialBalance;
use App\Domain\Reporting\VatOverview;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetDashboardOverviewTest extends TestCase
{
    private const ADMINISTRATION_A = '10000000-0000-4000-8000-000000000001';

    private const ADMINISTRATION_B = '20000000-0000-4000-8000-000000000001';

    private LedgerAccountReadRepository&MockObject $ledgerAccounts;

    private JournalEntryReadRepository&MockObject $journalEntries;

    private OpenItemReadRepository&MockObject $openItems;

    private TaxPostingReadRepository&MockObject $taxPostings;

    protected function setUp(): void
    {
        $this->ledgerAccounts = $this->createMock(LedgerAccountReadRepository::class);
        $this->journalEntries = $this->createMock(JournalEntryReadRepository::class);
        $this->openItems = $this->createMock(OpenItemReadRepository::class);
        $this->taxPostings = $this->createMock(TaxPostingReadRepository::class);
    }

    public function test_empty_financial_state_returns_four_exact_zero_amounts(): void
    {
        $result = $this->execute([], [], [], []);

        self::assertSame('0', $result->revenue()->amount());
        self::assertSame('0', $result->outstandingReceivables()->amount());
        self::assertSame('0', $result->outstandingPayables()->amount());
        self::assertSame('0', $result->vatPosition()->amount());
        self::assertSame('EUR', $result->currency()->code());
        self::assertSame('2026-08-01', $result->periodStart()->value()->format('Y-m-d'));
        self::assertSame('2026-08-20', $result->periodEnd()->value()->format('Y-m-d'));
    }

    public function test_existing_reporting_produces_revenue_open_items_and_reversal_aware_vat_without_tenant_leakage(): void
    {
        $accounts = $this->accounts();
        $entries = [
            $this->entry(1, self::ADMINISTRATION_A, '2026-08-10', '100', JournalEntryStatus::Posted),
            $this->entry(2, self::ADMINISTRATION_A, '2026-08-11', '200', JournalEntryStatus::Draft),
            $this->entry(3, self::ADMINISTRATION_A, '2026-07-31', '300', JournalEntryStatus::Posted),
            $this->entry(4, self::ADMINISTRATION_B, '2026-08-10', '400', JournalEntryStatus::Posted),
        ];
        $receivable = $this->openItem(1, OpenItemType::Receivable, '100');
        $receivable->applySettlement($this->settlementId(1), $this->date('2026-08-15'), $this->money('40'), $this->journalEntryId(20));
        $closed = $this->openItem(2, OpenItemType::Receivable, '50');
        $closed->applySettlement($this->settlementId(2), $this->date('2026-08-15'), $this->money('50'), $this->journalEntryId(21));
        $payable = $this->openItem(3, OpenItemType::Payable, '80');
        $postings = [
            $this->taxPosting(1, TaxPostingDirection::Output, '21'),
            $this->taxPosting(2, TaxPostingDirection::Input, '10'),
            $this->taxPosting(3, TaxPostingDirection::Output, '5', TaxPostingType::Reversal, 1),
            $this->taxPosting(4, TaxPostingDirection::Output, '99', administration: self::ADMINISTRATION_B),
        ];

        $result = $this->execute($accounts, $entries, [$receivable, $closed, $payable], $postings);

        self::assertSame('100', $result->revenue()->amount());
        self::assertSame('60', $result->outstandingReceivables()->amount());
        self::assertSame('80', $result->outstandingPayables()->amount());
        self::assertSame('6', $result->vatPosition()->amount());
        foreach ([$result->revenue(), $result->outstandingReceivables(), $result->outstandingPayables(), $result->vatPosition()] as $money) {
            self::assertSame('EUR', $money->currency()->code());
        }
    }

    public function test_input_vat_above_output_vat_returns_negative_position(): void
    {
        $result = $this->execute([], [], [], [
            $this->taxPosting(1, TaxPostingDirection::Output, '5'),
            $this->taxPosting(2, TaxPostingDirection::Input, '20'),
        ]);

        self::assertSame('-15', $result->vatPosition()->amount());
    }

    /** @param list<LedgerAccount> $accounts
     * @param  list<JournalEntry>  $entries
     * @param  list<OpenItem>  $openItems
     * @param  list<TaxPosting>  $taxPostings
     */
    private function execute(array $accounts, array $entries, array $openItems, array $taxPostings): DashboardOverview
    {
        $administration = $this->administration(self::ADMINISTRATION_A);
        $start = $this->date('2026-08-01');
        $end = $this->date('2026-08-20');
        $this->ledgerAccounts->expects(self::once())->method('findForAdministration')->with($administration)->willReturn($accounts);
        $this->journalEntries->expects(self::once())->method('findPostedForAdministrationAndPeriod')->with($administration, $start, $end)->willReturn($entries);
        $this->openItems->expects(self::once())->method('findForAdministrationAsOf')->with($administration, $end)->willReturn($openItems);
        $this->taxPostings->expects(self::once())->method('findForAdministrationAndPeriod')->with($administration, $start, $end)->willReturn($taxPostings);

        return (new GetDashboardOverview(
            $this->ledgerAccounts,
            $this->journalEntries,
            $this->openItems,
            $this->taxPostings,
            new TrialBalance,
            new ProfitAndLoss,
            new OpenItemsReport,
            new VatOverview,
        ))->execute($administration, $start, $end, new Currency('EUR'));
    }

    /** @return list<LedgerAccount> */
    private function accounts(): array
    {
        return [
            new LedgerAccount($this->accountId(1), new LedgerAccountCode('1000'), new LedgerAccountName('Debtors'), LedgerAccountType::Asset, LedgerAccountStatus::Active),
            new LedgerAccount($this->accountId(2), new LedgerAccountCode('8000'), new LedgerAccountName('Revenue'), LedgerAccountType::Revenue, LedgerAccountStatus::Active),
        ];
    }

    private function entry(int $sequence, string $administration, string $date, string $amount, JournalEntryStatus $status): JournalEntry
    {
        return JournalEntry::reconstitute(
            $this->journalEntryId($sequence),
            $this->administration($administration),
            new JournalId($this->uuid('7', $sequence)),
            $this->date($date),
            new JournalEntryReference('Entry '.$sequence),
            $status,
            [
                new JournalEntryLine($this->lineId($sequence * 2), $this->accountId(1), $this->money($amount), null, 'Debit'),
                new JournalEntryLine($this->lineId(($sequence * 2) + 1), $this->accountId(2), null, $this->money($amount), 'Credit'),
            ],
        );
    }

    private function openItem(int $sequence, OpenItemType $type, string $amount): OpenItem
    {
        return new OpenItem(
            new OpenItemId($this->uuid('3', $sequence)),
            $this->administration(self::ADMINISTRATION_A),
            new RelationId($this->uuid('4', $sequence)),
            $this->journalEntryId(10 + $sequence),
            $type,
            $this->money($amount),
            $this->date('2026-08-01'),
        );
    }

    private function taxPosting(
        int $sequence,
        TaxPostingDirection $direction,
        string $tax,
        TaxPostingType $type = TaxPostingType::Original,
        ?int $reversed = null,
        string $administration = self::ADMINISTRATION_A,
    ): TaxPosting {
        return new TaxPosting(
            new TaxPostingId($this->uuid('8', $sequence)),
            $this->administration($administration),
            new TaxCodeId($this->uuid('9', 1)),
            new TaxRate('21'),
            $this->money('100'),
            $this->money($tax),
            $direction,
            TaxSourceDocumentType::SalesInvoice,
            new TaxSourceDocumentId($this->uuid('a', $sequence)),
            new TaxSourceLineId($this->uuid('b', $sequence)),
            $this->date('2026-08-10'),
            $this->journalEntryId(30 + $sequence),
            $this->lineId(30 + $sequence),
            $this->lineId(40 + $sequence),
            $type,
            $reversed === null ? null : new TaxPostingId($this->uuid('8', $reversed)),
        );
    }

    private function administration(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function date(string $date): PostingDate
    {
        return new PostingDate(new \DateTimeImmutable($date));
    }

    private function money(string $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }

    private function accountId(int $sequence): LedgerAccountId
    {
        return new LedgerAccountId($this->uuid('1', $sequence));
    }

    private function journalEntryId(int $sequence): JournalEntryId
    {
        return new JournalEntryId($this->uuid('2', $sequence));
    }

    private function lineId(int $sequence): JournalEntryLineId
    {
        return new JournalEntryLineId($this->uuid('5', $sequence));
    }

    private function settlementId(int $sequence): OpenItemSettlementId
    {
        return new OpenItemSettlementId($this->uuid('6', $sequence));
    }

    private function uuid(string $prefix, int $sequence): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $sequence));
    }
}
