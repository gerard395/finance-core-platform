<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class GetBankTransactionReversalReadiness
{
    public function __construct(private BankTransactionReversalSourceReader $sources, private AssessBankTransactionReversalEligibility $eligibility, private BankTransactionSettlementReversalLinkRepository $settlementLinks) {}

    public function execute(AdministrationId $administrationId, BankTransactionId $bankTransactionId): ?BankTransactionReversalReadiness
    {
        $source = $this->sources->read($administrationId, $bankTransactionId);
        if ($source === null) {
            return null;
        }
        $transaction = $source->transaction;
        $payment = $transaction->paymentOrNull();
        $other = $transaction->otherIntentOrNull();

        $reversedSettlementCount = $source->reversal === null
            ? 0
            : count($this->settlementLinks->findByReversal($administrationId, $source->reversal->id));

        return new BankTransactionReversalReadiness(
            $this->eligibility->forSource($source),
            $transaction->id(),
            $transaction->intentType(),
            $payment?->type(),
            $transaction->amount(),
            $payment?->relationId(),
            $other?->contraLedgerAccountId(),
            $transaction->description(),
            $transaction->transactionDate()->value(),
            $source->posting?->postingDate,
            $source->posting?->journalEntryId,
            count($payment?->allocations() ?? []),
            count($source->settlements),
            $reversedSettlementCount,
            $source->reversal?->id,
            $source->reversal,
        );
    }
}
