<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class GeneralLedgerReport
{
    /** @param list<JournalEntry> $journalEntries */
    public function generate(
        array $journalEntries,
        AdministrationId $administrationId,
        Currency $currency,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?LedgerAccountId $ledgerAccountId = null,
    ): GeneralLedgerResult {
        if ($startDate > $endDate) {
            throw new DomainException('General ledger start date cannot be after end date.');
        }

        $sourceLines = [];

        foreach ($journalEntries as $journalEntry) {
            $postingDate = $journalEntry->postingDate()->value();

            if (! $journalEntry->isPosted()
                || ! $journalEntry->administrationId()->equals($administrationId)
                || $postingDate < $startDate
                || $postingDate > $endDate) {
                continue;
            }

            foreach ($journalEntry->lines() as $journalEntryLine) {
                if ($ledgerAccountId !== null && ! $journalEntryLine->ledgerAccountId()->equals($ledgerAccountId)) {
                    continue;
                }

                $sourceLines[] = [$journalEntry, $journalEntryLine];
            }
        }

        usort($sourceLines, static function (array $left, array $right): int {
            /** @var JournalEntry $leftEntry */
            $leftEntry = $left[0];
            /** @var JournalEntry $rightEntry */
            $rightEntry = $right[0];
            $dateOrder = $leftEntry->postingDate()->value() <=> $rightEntry->postingDate()->value();

            if ($dateOrder !== 0) {
                return $dateOrder;
            }

            $entryOrder = strcmp($leftEntry->id()->toString(), $rightEntry->id()->toString());

            if ($entryOrder !== 0) {
                return $entryOrder;
            }

            return strcmp($left[1]->id()->toString(), $right[1]->id()->toString());
        });

        $lines = [];
        $totalDebit = Money::zero($currency);
        $totalCredit = Money::zero($currency);
        $runningBalance = Money::zero($currency);

        foreach ($sourceLines as [$journalEntry, $journalEntryLine]) {
            $debit = $journalEntryLine->debit() ?? Money::zero($currency);
            $credit = $journalEntryLine->credit() ?? Money::zero($currency);
            $runningBalance = $runningBalance->add($debit)->subtract($credit);
            $totalDebit = $totalDebit->add($debit);
            $totalCredit = $totalCredit->add($credit);
            $lines[] = new GeneralLedgerLine(
                $journalEntry->postingDate(),
                $journalEntry->id(),
                $journalEntryLine->id(),
                $journalEntry->journalId(),
                $journalEntry->reference(),
                $journalEntryLine->ledgerAccountId(),
                $debit,
                $credit,
                $runningBalance,
            );
        }

        return new GeneralLedgerResult(
            $administrationId,
            $startDate,
            $endDate,
            $currency,
            $lines,
            $totalDebit,
            $totalCredit,
            $totalDebit->subtract($totalCredit),
        );
    }
}
