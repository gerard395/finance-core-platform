<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Identity\ValueObjects\UserId;
use Throwable;

final readonly class ReverseReconciledBankTransaction
{
    public function __construct(private TransactionManager $transactions, private BankEntryFinancialReconciliationStore $reconciliations, private ReverseBankTransaction $reverse) {}

    public function execute(AdministrationId $administrationId, BankTransactionId $transactionId, PostingDate $postingDate, BankTransactionReversalReason $reason, UserId $actor): ReverseBankTransactionResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $transactionId, $postingDate, $reason, $actor): ReverseBankTransactionResult {
                $reconciliation = $this->reconciliations->byTransaction($administrationId, $transactionId);
                if ($reconciliation === null || $this->reconciliations->lockSource($administrationId, $reconciliation->entryId) === null) {
                    return new ReverseBankTransactionResult(ReverseBankTransactionStatus::NotFound);
                }
                $active = $this->reconciliations->active($administrationId, $reconciliation->entryId);
                if ($active?->id->toString() !== $reconciliation->id->toString()) {
                    return new ReverseBankTransactionResult(ReverseBankTransactionStatus::AlreadyReversed);
                }
                $result = $this->reverse->execute($administrationId, $transactionId, $postingDate, $reason, $actor);
                if ($result->status === ReverseBankTransactionStatus::Success && ! $this->reconciliations->deactivate($administrationId, $reconciliation->entryId, $reconciliation->id)) {
                    throw new \RuntimeException('Active reconciliation could not be removed.');
                }

                return $result;
            });
        } catch (Throwable) {
            return new ReverseBankTransactionResult(ReverseBankTransactionStatus::PostingFailure);
        }
    }
}
