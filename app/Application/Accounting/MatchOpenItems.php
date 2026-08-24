<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Services\OpenItemMatchingPolicy;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Money;
use DomainException;
use Throwable;

final readonly class MatchOpenItems
{
    public function __construct(
        private TransactionManager $transactions,
        private OpenItemMatchRepository $matches,
        private OpenItemMatchIdentityGenerator $identities,
        private OpenItemMatchingPolicy $policy,
    ) {}

    public function execute(
        AdministrationId $administrationId,
        OpenItemId $debitOpenItemId,
        OpenItemId $creditOpenItemId,
        Money $amount,
        PostingDate $occurredOn,
        JournalEntryId $sourceJournalEntryId,
    ): MatchOpenItemsResult {
        return $this->run($administrationId, $debitOpenItemId, $creditOpenItemId, $amount, $occurredOn, $sourceJournalEntryId);
    }

    public function executeAvailable(
        AdministrationId $administrationId,
        OpenItemId $debitOpenItemId,
        OpenItemId $creditOpenItemId,
        PostingDate $occurredOn,
        JournalEntryId $sourceJournalEntryId,
    ): MatchOpenItemsResult {
        return $this->run($administrationId, $debitOpenItemId, $creditOpenItemId, null, $occurredOn, $sourceJournalEntryId);
    }

    private function run(
        AdministrationId $administrationId,
        OpenItemId $debitOpenItemId,
        OpenItemId $creditOpenItemId,
        ?Money $requestedAmount,
        PostingDate $occurredOn,
        JournalEntryId $sourceJournalEntryId,
    ): MatchOpenItemsResult {
        try {
            return $this->transactions->run(function () use ($administrationId, $debitOpenItemId, $creditOpenItemId, $requestedAmount, $occurredOn, $sourceJournalEntryId): MatchOpenItemsResult {
                $pair = $this->matches->findLockedPair($administrationId, $debitOpenItemId, $creditOpenItemId);
                if ($pair === null) {
                    return new MatchOpenItemsResult(MatchOpenItemsStatus::NotFound);
                }

                $amount = $requestedAmount;
                if ($amount === null) {
                    $debitOpen = $pair->debit->openAmountAt($occurredOn);
                    $creditOpen = $pair->credit->openAmountAt($occurredOn);
                    if ($debitOpen->isZero() || $creditOpen->isZero()) {
                        return new MatchOpenItemsResult(MatchOpenItemsStatus::NothingToMatch);
                    }
                    $amount = $debitOpen->subtract($creditOpen)->isPositive() ? $creditOpen : $debitOpen;
                }

                try {
                    $match = $this->policy->create($this->identities->next(), $pair->debit, $pair->credit, $amount, $occurredOn, $sourceJournalEntryId);
                } catch (DomainException) {
                    return new MatchOpenItemsResult(MatchOpenItemsStatus::InvalidMatch);
                }

                $result = $this->matches->appendMatch($match);

                return $result === OpenItemMatchAppendResult::Appended
                    ? new MatchOpenItemsResult(MatchOpenItemsStatus::Success, $match->id())
                    : new MatchOpenItemsResult(MatchOpenItemsStatus::AlreadyExists);
            });
        } catch (Throwable) {
            return new MatchOpenItemsResult(MatchOpenItemsStatus::PersistenceFailure);
        }
    }
}
