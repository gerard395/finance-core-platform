<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Enums\BankTransactionStatus;
use DomainException;

final class CreateBankTransactionPostingRequest
{
    public function execute(
        BankTransaction $transaction,
        JournalId $bankJournalId,
        LedgerAccountId $bankLedgerAccountId,
        LedgerAccountId $counterLedgerAccountId,
        JournalEntryLineId $bankLineId,
        JournalEntryLineId $counterLineId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): PostingRequest {
        if ($transaction->status() !== BankTransactionStatus::Matched) {
            throw new DomainException('A bank transaction must be matched before a posting request can be created.');
        }

        if ($transaction->payments() === []) {
            throw new DomainException('A matched bank transaction must contain at least one payment.');
        }

        $amount = $transaction->amount()->absolute();

        if ($amount->isZero()) {
            throw new DomainException('A bank transaction posting amount must be greater than zero.');
        }

        $description = 'Bank transaction '.$transaction->reference()->value();

        if ($transaction->amount()->isPositive()) {
            $bankLine = new JournalEntryLine($bankLineId, $bankLedgerAccountId, $amount, null, $description);
            $counterLine = new JournalEntryLine($counterLineId, $counterLedgerAccountId, null, $amount, $description);
        } else {
            $bankLine = new JournalEntryLine($bankLineId, $bankLedgerAccountId, null, $amount, $description);
            $counterLine = new JournalEntryLine($counterLineId, $counterLedgerAccountId, $amount, null, $description);
        }

        return new PostingRequest(
            $transaction->administrationId(),
            $bankJournalId,
            $postingDate,
            $reference,
            [$bankLine, $counterLine],
        );
    }
}
