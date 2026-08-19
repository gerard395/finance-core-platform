<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class BalanceSheet
{
    /** @param list<LedgerAccount> $ledgerAccounts */
    public function create(
        TrialBalanceResult $trialBalanceResult,
        array $ledgerAccounts,
        DateTimeImmutable $balanceDate,
    ): BalanceSheetResult {
        if ($balanceDate != $trialBalanceResult->endDate()) {
            throw new DomainException('Balance sheet date must equal the trial balance end date.');
        }

        $accounts = [];

        foreach ($ledgerAccounts as $ledgerAccount) {
            $key = $ledgerAccount->id()->toString();

            if (isset($accounts[$key])) {
                throw new DomainException('A ledger account can occur only once in a balance sheet.');
            }

            $accounts[$key] = $ledgerAccount;
        }

        $assets = [];
        $liabilities = [];
        $equity = [];
        $currency = $trialBalanceResult->currency();
        $totalAssets = Money::zero($currency);
        $totalLiabilities = Money::zero($currency);
        $totalEquity = Money::zero($currency);

        foreach ($trialBalanceResult->lines() as $trialBalanceLine) {
            $key = $trialBalanceLine->ledgerAccountId()->toString();
            $ledgerAccount = $accounts[$key] ?? null;

            if ($ledgerAccount === null) {
                throw new DomainException('A trial balance line references a ledger account outside the balance sheet.');
            }

            $type = $ledgerAccount->type();

            if ($type === LedgerAccountType::Revenue || $type === LedgerAccountType::Expense) {
                continue;
            }

            $balance = $type === LedgerAccountType::Asset
                ? $trialBalanceLine->balance()
                : $trialBalanceLine->balance()->absolute();
            $line = new BalanceSheetLine($ledgerAccount->id(), $type, $balance);

            if ($type === LedgerAccountType::Asset) {
                $assets[] = $line;
                $totalAssets = $totalAssets->add($balance);
            } elseif ($type === LedgerAccountType::Liability) {
                $liabilities[] = $line;
                $totalLiabilities = $totalLiabilities->add($balance);
            } else {
                $equity[] = $line;
                $totalEquity = $totalEquity->add($balance);
            }
        }

        return new BalanceSheetResult(
            $trialBalanceResult->administrationId(),
            $trialBalanceResult->endDate(),
            $currency,
            $assets,
            $liabilities,
            $equity,
            $totalAssets,
            $totalLiabilities,
            $totalEquity,
        );
    }
}
