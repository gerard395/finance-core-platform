<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Relations\ValueObjects\RelationId;
use InvalidArgumentException;

final readonly class PrepareBankReconciliationAllocations
{
    public function __construct(private BankReconciliationCandidateReader $candidates) {}

    /** @param list<RequestedBankAllocation> $requested @return list<PreparedPaymentAllocation> */
    public function execute(AdministrationId $administrationId, PostingDate $postingDate, BankEntryReconciliationIntent $intent, RelationId $relationId, array $requested): array
    {
        if ($intent === BankEntryReconciliationIntent::Other) {
            throw new InvalidArgumentException('Other transactions cannot have payment allocations.');
        }
        $eligible = [];
        foreach ($this->candidates->eligible($administrationId, $postingDate) as $candidate) {
            $eligible[$candidate->openItemId->toString()] = $candidate;
        }
        $expectedType = $intent === BankEntryReconciliationIntent::CustomerReceipt ? OpenItemType::Receivable : OpenItemType::Payable;
        $expectedSide = $intent === BankEntryReconciliationIntent::CustomerReceipt ? OpenItemSide::Debit : OpenItemSide::Credit;
        $result = [];
        foreach ($requested as $allocation) {
            $candidate = $eligible[$allocation->openItemId->toString()] ?? null;
            if ($candidate === null || ! $candidate->relationId->equals($relationId) || $candidate->type !== $expectedType || $candidate->side !== $expectedSide) {
                throw new InvalidArgumentException('Allocation is not an eligible tenant-scoped candidate.');
            }
            $result[] = new PreparedPaymentAllocation($candidate->openItemId, $candidate->relationId, $candidate->controlLedgerAccountId, $allocation->amount, $candidate->openBalance, ['manual_web_selection']);
        }

        return $result;
    }
}
