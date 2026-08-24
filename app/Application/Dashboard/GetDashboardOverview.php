<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Accounting\JournalEntryReadRepository;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Fiscal\TaxPostingReadRepository;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Reporting\OpenItemsReport;
use App\Domain\Reporting\ProfitAndLoss;
use App\Domain\Reporting\TrialBalance;
use App\Domain\Reporting\VatOverview;
use App\Domain\Shared\Finance\Currency;

final readonly class GetDashboardOverview
{
    public function __construct(
        private LedgerAccountReadRepository $ledgerAccounts,
        private JournalEntryReadRepository $journalEntries,
        private OpenItemReadRepository $openItems,
        private TaxPostingReadRepository $taxPostings,
        private TrialBalance $trialBalance,
        private ProfitAndLoss $profitAndLoss,
        private OpenItemsReport $openItemsReport,
        private VatOverview $vatOverview,
    ) {}

    public function execute(
        AdministrationId $administrationId,
        PostingDate $periodStart,
        PostingDate $periodEnd,
        Currency $currency,
    ): DashboardOverview {
        $accounts = $this->ledgerAccounts->findForAdministration($administrationId);
        $entries = $this->journalEntries->findPostedForAdministrationAndPeriod($administrationId, $periodStart, $periodEnd);
        $profitAndLoss = $this->profitAndLoss->create(
            $this->trialBalance->calculate($accounts, $entries, $administrationId, $periodStart, $periodEnd, $currency),
            $accounts,
        );

        $receivables = [];
        $payables = [];

        foreach ($this->openItems->findForAdministrationAsOf($administrationId, $periodEnd) as $openItem) {
            if ($openItem->type() === OpenItemType::Receivable) {
                $receivables[] = $openItem;
            } else {
                $payables[] = $openItem;
            }
        }

        $outstandingReceivables = $this->openItemsReport
            ->generate($receivables, $administrationId, $currency, $periodEnd)
            ->netReceivableOpenAmount();
        $outstandingPayables = $this->openItemsReport
            ->generate($payables, $administrationId, $currency, $periodEnd)
            ->totalOpenAmount();
        $vat = $this->vatOverview->calculate(
            $this->taxPostings->findForAdministrationAndPeriod($administrationId, $periodStart, $periodEnd),
            $administrationId,
            $currency,
            $periodStart->value(),
            $periodEnd->value(),
        );

        return new DashboardOverview(
            $administrationId,
            $periodStart,
            $periodEnd,
            $currency,
            $profitAndLoss->totalRevenue(),
            $outstandingReceivables,
            $outstandingPayables,
            $vat->netVatPosition(),
        );
    }
}
