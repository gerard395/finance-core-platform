<?php

declare(strict_types=1);

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\MatchingError;
use App\Domain\Banking\ValueObjects\MatchingResult;
use App\Domain\Shared\Finance\Money;

final class Matching
{
    public function match(BankTransaction $transaction): MatchingResult
    {
        $matchedAmount = Money::zero($transaction->amount()->currency());

        foreach ($transaction->payments() as $payment) {
            $matchedAmount = $matchedAmount->add($payment->amount());
        }

        if ($transaction->status() === BankTransactionStatus::Posted) {
            return new MatchingResult(
                $transaction,
                $matchedAmount,
                [new MatchingError(MatchingError::INVALID_STATUS, 'A posted bank transaction cannot be matched.')],
            );
        }

        if ($transaction->status() === BankTransactionStatus::Matched) {
            return new MatchingResult($transaction, $matchedAmount);
        }

        if ($transaction->payments() === []) {
            return new MatchingResult(
                $transaction,
                $matchedAmount,
                [new MatchingError(MatchingError::NO_PAYMENTS, 'A bank transaction must contain at least one payment before matching.')],
            );
        }

        if (! $matchedAmount->equals($transaction->amount()->absolute())) {
            return new MatchingResult(
                $transaction,
                $matchedAmount,
                [new MatchingError(MatchingError::AMOUNT_MISMATCH, 'The allocated payment amount must equal the absolute bank transaction amount.')],
            );
        }

        $transaction->match();

        return new MatchingResult($transaction, $matchedAmount);
    }
}
