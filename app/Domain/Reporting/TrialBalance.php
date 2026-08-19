<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class TrialBalance
{
    /**
     * @param  list<LedgerAccount>  $ledgerAccounts
     * @param  list<JournalEntry>  $journalEntries
     */
    public function calculate(
        array $ledgerAccounts,
        array $journalEntries,
        AdministrationId $administrationId,
        PostingDate $from,
        PostingDate $to,
        Currency $currency,
    ): TrialBalanceResult {
        if ($from->value() > $to->value()) {
            throw new DomainException('Trial balance start date cannot be after end date.');
        }

        $accounts = [];
        $debits = [];
        $credits = [];

        foreach ($ledgerAccounts as $ledgerAccount) {
            $key = $ledgerAccount->id()->toString();

            if (isset($accounts[$key])) {
                throw new DomainException('A ledger account can occur only once in a trial balance.');
            }

            $accounts[$key] = $ledgerAccount;
            $debits[$key] = Money::zero($currency);
            $credits[$key] = Money::zero($currency);
        }

        foreach ($journalEntries as $journalEntry) {
            if (! $this->isIncluded($journalEntry, $administrationId, $from, $to)) {
                continue;
            }

            foreach ($journalEntry->lines() as $entryLine) {
                $key = $entryLine->ledgerAccountId()->toString();

                if (! isset($accounts[$key])) {
                    throw new DomainException('A journal entry line references a ledger account outside the trial balance.');
                }

                $debits[$key] = $debits[$key]->add($entryLine->debit() ?? Money::zero($currency));
                $credits[$key] = $credits[$key]->add($entryLine->credit() ?? Money::zero($currency));
            }
        }

        $lines = [];
        $totalDebit = Money::zero($currency);
        $totalCredit = Money::zero($currency);

        foreach ($accounts as $key => $ledgerAccount) {
            $debit = $debits[$key];
            $credit = $credits[$key];
            $balance = $debit->subtract($credit);
            $lines[] = new TrialBalanceLine($ledgerAccount->id(), $debit, $credit, $balance);
            $totalDebit = $totalDebit->add($debit);
            $totalCredit = $totalCredit->add($credit);
        }

        return new TrialBalanceResult(
            $lines,
            $totalDebit,
            $totalCredit,
            $administrationId,
            $from->value(),
            $to->value(),
            $currency,
        );
    }

    private function isIncluded(
        JournalEntry $journalEntry,
        AdministrationId $administrationId,
        PostingDate $from,
        PostingDate $to,
    ): bool {
        $postingDate = $journalEntry->postingDate()->value();

        return $journalEntry->isPosted()
            && $journalEntry->administrationId()->equals($administrationId)
            && $postingDate >= $from->value()
            && $postingDate <= $to->value();
    }
}
