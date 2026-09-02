<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;

final readonly class AccountingPeriodHistoryOrderer
{
    /**
     * @param  list<AccountingPeriodHistoryEntry>  $history
     * @return list<AccountingPeriodHistoryEntry>
     */
    public function order(array $history, AccountingPeriodStatus $currentStatus): array
    {
        $byTimestamp = [];
        foreach ($history as $entry) {
            $byTimestamp[$entry->occurredAt->format('Y-m-d H:i:s')][] = $entry;
        }
        ksort($byTimestamp);

        $status = AccountingPeriodStatus::Open;
        $ordered = [];
        foreach ($byTimestamp as $sameTimestamp) {
            while ($sameTimestamp !== []) {
                $candidates = array_keys(array_filter(
                    $sameTimestamp,
                    static fn (AccountingPeriodHistoryEntry $entry): bool => $entry->fromStatus === $status,
                ));
                if (count($candidates) !== 1) {
                    throw new AccountingPeriodHistoryIntegrityException('Accounting period history has no unique causal order.');
                }

                $index = $candidates[0];
                $entry = $sameTimestamp[$index];
                $ordered[] = $entry;
                $status = $entry->toStatus;
                unset($sameTimestamp[$index]);
            }
        }

        if ($status !== $currentStatus) {
            throw new AccountingPeriodHistoryIntegrityException('Accounting period history does not match its current status.');
        }

        return $ordered;
    }
}
