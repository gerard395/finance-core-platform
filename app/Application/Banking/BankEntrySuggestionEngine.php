<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Shared\Finance\Money;

final readonly class BankEntrySuggestionEngine
{
    /** @param list<BankReconciliationCandidate> $candidates */
    public function suggest(BankStatementEntry $entry, array $candidates): BankEntrySuggestion
    {
        $intent = $entry->direction === BankEntryDirection::Credit ? BankEntrySuggestionIntent::CustomerReceipt : BankEntrySuggestionIntent::SupplierPayment;
        $requiredType = $entry->direction === BankEntryDirection::Credit ? OpenItemType::Receivable : OpenItemType::Payable;
        $requiredSide = $entry->direction === BankEntryDirection::Credit ? OpenItemSide::Debit : OpenItemSide::Credit;
        $eligible = array_values(array_filter($candidates, static fn (BankReconciliationCandidate $candidate): bool => $candidate->type === $requiredType && $candidate->side === $requiredSide && $candidate->openBalance->isPositive() && $candidate->openBalance->currency()->equals($entry->amount->currency()) && $candidate->openedOn <= $entry->bookingDate));
        if ($eligible === []) {
            return new BankEntrySuggestion(BankEntrySuggestionIntent::Other, BankEntrySuggestionConfidence::Low, BankEntrySuggestionOutcome::Other, ['no_eligible_open_item']);
        }

        $ranked = [];
        foreach ($eligible as $candidate) {
            $evidence = $this->evidence($entry, $candidate);
            $ranked[$candidate->relationId->toString()][] = [$candidate, $evidence, $this->score($evidence)];
        }
        $relationScores = [];
        foreach ($ranked as $relation => $items) {
            $relationScores[$relation] = max(array_column($items, 2));
        }
        arsort($relationScores, SORT_NUMERIC);
        $best = reset($relationScores);
        $winners = array_keys(array_filter($relationScores, static fn (int $score): bool => $score === $best));
        if (count($winners) !== 1) {
            return new BankEntrySuggestion($intent, $this->confidence((int) $best), BankEntrySuggestionOutcome::Ambiguous, ['equal_strength_candidates'], null, [], array_map(static fn (string $id) => $ranked[$id][0][0]->relationId, $winners));
        }

        $relation = $winners[0];
        usort($ranked[$relation], static fn (array $a, array $b): int => $b[2] <=> $a[2] ?: strcmp($a[0]->openItemId->toString(), $b[0]->openItemId->toString()));
        $remaining = $entry->amount->absolute();
        $allocations = [];
        $allEvidence = [];
        foreach ($ranked[$relation] as [$candidate, $evidence]) {
            if ($remaining->isZero()) {
                break;
            }
            $amount = $this->minimum($remaining, $candidate->openBalance);
            $allocations[] = new PreparedPaymentAllocation($candidate->openItemId, $candidate->relationId, $candidate->controlLedgerAccountId, $amount, $candidate->openBalance, $evidence);
            $remaining = $remaining->subtract($amount);
            $allEvidence = array_values(array_unique([...$allEvidence, ...$evidence]));
        }
        $outcome = $remaining->isZero() ? BankEntrySuggestionOutcome::PaymentReady : BankEntrySuggestionOutcome::AllocationIncomplete;

        return new BankEntrySuggestion($intent, $this->confidence((int) $best), $outcome, $allEvidence, $ranked[$relation][0][0]->relationId, $allocations);
    }

    /** @return list<string> */
    private function evidence(BankStatementEntry $entry, BankReconciliationCandidate $candidate): array
    {
        $haystack = $this->normalize(implode(' ', array_filter([$entry->accountServicerReference, $entry->entryReference, $entry->endToEndId, $entry->creditorReference, ...$entry->remittanceLines])));
        $reference = $this->normalize($candidate->documentReference);
        $evidence = [];
        if ($reference !== '' && str_contains($haystack, $reference)) {
            $evidence[] = 'exact_document_reference';
        }
        if ($entry->creditorReference !== null && $this->normalize($entry->creditorReference) === $reference) {
            $evidence[] = 'structured_creditor_reference';
        }
        $account = $this->normalizeAccount($entry->counterpartyAccount);
        if ($account !== '' && in_array($account, array_map($this->normalizeAccount(...), $candidate->relationIbans), true)) {
            $evidence[] = 'known_relation_iban';
        }
        if ($entry->amount->absolute()->equals($candidate->openBalance)) {
            $evidence[] = 'exact_open_amount';
        }
        if ($entry->counterpartyName !== null && str_contains($this->normalize($entry->counterpartyName), $this->normalize($candidate->relationName))) {
            $evidence[] = 'counterparty_name';
        }
        if ($candidate->dueDate !== null && abs($candidate->dueDate->diff($entry->bookingDate)->days) <= 14) {
            $evidence[] = 'due_date_proximity';
        }

        return $evidence === [] ? ['amount_and_side_only'] : $evidence;
    }

    /** @param list<string> $evidence */
    private function score(array $evidence): int
    {
        $weights = ['exact_document_reference' => 5000, 'structured_creditor_reference' => 5000, 'known_relation_iban' => 3500, 'exact_open_amount' => 2500, 'counterparty_name' => 1200, 'due_date_proximity' => 500, 'amount_and_side_only' => 100];

        return min(10000, array_sum(array_map(static fn (string $item): int => $weights[$item], $evidence)));
    }

    private function confidence(int $score): BankEntrySuggestionConfidence
    {
        return match (true) {
            $score >= 10000 => BankEntrySuggestionConfidence::Exact, $score >= 7000 => BankEntrySuggestionConfidence::High, $score >= 4000 => BankEntrySuggestionConfidence::Medium, default => BankEntrySuggestionConfidence::Low
        };
    }

    private function minimum(Money $left, Money $right): Money
    {
        return $left->subtract($right)->isNegative() ? $left : $right;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', '', $value) ?? ''));
    }

    private function normalizeAccount(?string $value): string
    {
        return strtoupper(str_replace(' ', '', $value ?? ''));
    }
}
