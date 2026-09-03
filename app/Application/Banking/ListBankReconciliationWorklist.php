<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use DateTimeImmutable;

final readonly class ListBankReconciliationWorklist
{
    public function __construct(private BankReconciliationSourceReader $sources, private BankReconciliationCandidateReader $candidates, private BankEntrySuggestionEngine $suggestions) {}

    /** @return list<BankReconciliationWorklistItem> */
    public function execute(AdministrationId $administrationId, BankReconciliationWorklistFilter $filter): array
    {
        $sources = $this->sources->list($administrationId, $filter);
        if ($sources === []) {
            return [];
        }
        $latestDate = max(array_map(static fn (BankReconciliationSourceItem $item): string => $item->entry->bookingDate->format('Y-m-d'), $sources));
        $candidates = $this->candidates->eligible($administrationId, new PostingDate(new DateTimeImmutable($latestDate)));

        return array_map(fn (BankReconciliationSourceItem $item): BankReconciliationWorklistItem => new BankReconciliationWorklistItem($item, $item->state === BankEntryDerivedState::Unresolved ? $this->suggestions->suggest($item->entry, $candidates) : null), $sources);
    }
}
