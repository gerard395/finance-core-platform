<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\Enums\BankEntryManualAction;

final readonly class BankEntryManualHistoryOrderer
{
    /**
     * @param  list<BankEntryReconciliationHistory>  $history
     * @return list<BankEntryReconciliationHistory>
     */
    public function order(array $history): array
    {
        usort($history, static fn (BankEntryReconciliationHistory $left, BankEntryReconciliationHistory $right): int => ($left->sequence ?? 0) <=> ($right->sequence ?? 0));
        $previous = null;
        $ids = [];
        $sequences = [];
        foreach ($history as $event) {
            if ($event->sequence === null || isset($ids[$event->id->toString()]) || isset($sequences[$event->sequence])) {
                throw new BankEntryManualHistoryIntegrityException('Reconciliation history has duplicate or missing causal identity.');
            }
            if (($previous === null && ($event->predecessorId !== null || $event->action !== BankEntryManualAction::Ignore))
                || ($previous !== null && $event->predecessorId?->toString() !== $previous->id->toString())
                || ($previous?->action === BankEntryManualAction::Ignore && $event->action !== BankEntryManualAction::RestoreFromIgnored)
                || ($previous?->action === BankEntryManualAction::RestoreFromIgnored && $event->action !== BankEntryManualAction::Ignore)) {
                throw new BankEntryManualHistoryIntegrityException('Reconciliation history has no coherent causal chain.');
            }
            $ids[$event->id->toString()] = true;
            $sequences[$event->sequence] = true;
            $previous = $event;
        }

        return $history;
    }
}
