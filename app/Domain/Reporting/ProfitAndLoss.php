<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class ProfitAndLoss
{
    /** @param list<LedgerAccount> $ledgerAccounts */
    public function create(
        TrialBalanceResult $trialBalanceResult,
        array $ledgerAccounts,
    ): ProfitAndLossResult {
        $accounts = [];

        foreach ($ledgerAccounts as $ledgerAccount) {
            $key = $ledgerAccount->id()->toString();

            if (isset($accounts[$key])) {
                throw new DomainException('A ledger account can occur only once in a profit and loss report.');
            }

            $accounts[$key] = $ledgerAccount;
        }

        $revenue = [];
        $expenses = [];
        $currency = $trialBalanceResult->currency();
        $totalRevenue = Money::zero($currency);
        $totalExpenses = Money::zero($currency);

        foreach ($trialBalanceResult->lines() as $trialBalanceLine) {
            $key = $trialBalanceLine->ledgerAccountId()->toString();
            $ledgerAccount = $accounts[$key] ?? null;

            if ($ledgerAccount === null) {
                throw new DomainException('A trial balance line references a ledger account outside the profit and loss report.');
            }

            $type = $ledgerAccount->type();

            if ($type !== LedgerAccountType::Revenue && $type !== LedgerAccountType::Expense) {
                continue;
            }

            if ($type === LedgerAccountType::Revenue) {
                $amount = $trialBalanceLine->balance()->absolute();
                $revenue[] = new ProfitAndLossLine($ledgerAccount->id(), $type, $amount);
                $totalRevenue = $totalRevenue->add($amount);
            } else {
                $amount = $trialBalanceLine->balance();
                $expenses[] = new ProfitAndLossLine($ledgerAccount->id(), $type, $amount);
                $totalExpenses = $totalExpenses->add($amount);
            }
        }

        return new ProfitAndLossResult(
            $trialBalanceResult->administrationId(),
            $trialBalanceResult->startDate(),
            $trialBalanceResult->endDate(),
            $currency,
            $revenue,
            $expenses,
            $totalRevenue,
            $totalExpenses,
            $totalRevenue->subtract($totalExpenses),
        );
    }
}
