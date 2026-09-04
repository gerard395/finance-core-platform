<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\Entities\BankEntryReconciliation;

final class BankEntryFinancialHistoryOrderer
{
    /** @param list<BankEntryReconciliation> $history
     * @return list<BankEntryReconciliation>|null
     */
    public function order(array $history): ?array
    {
        if ($history === []) {
            return [];
        }
        $byParent = [];
        foreach ($history as $entry) {
            $byParent[$entry->replacesId?->toString() ?? 'root'][] = $entry;
        }
        if (count($byParent['root'] ?? []) !== 1) {
            return null;
        }
        $ordered = [];
        $seen = [];
        $current = $byParent['root'][0];
        while (true) {
            $id = $current->id->toString();
            if (isset($seen[$id])) {
                return null;
            }
            $seen[$id] = true;
            $ordered[] = $current;
            $children = $byParent[$id] ?? [];
            if ($children === []) {
                break;
            }
            if (count($children) !== 1) {
                return null;
            }
            $current = $children[0];
        }

        return count($ordered) === count($history) ? $ordered : null;
    }
}
