<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\OpenItemReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class GetBankTransactionPostingDetail
{
    public function __construct(
        private BankTransactionRepository $transactions,
        private BankTransactionPostingRepository $postings,
        private OpenItemReadRepository $openItems,
    ) {}

    public function execute(AdministrationId $administrationId, BankTransactionId $bankTransactionId): ?BankTransactionPostingDetail
    {
        $transaction = $this->transactions->find($administrationId, $bankTransactionId);
        $posting = $this->postings->find($administrationId, $bankTransactionId);

        if ($transaction === null || $posting === null) {
            return null;
        }

        $settlements = [];
        foreach ($transaction->payment()->allocations() as $allocation) {
            $settlementAmount = $this->postings->settlementAmount($administrationId, $allocation->id());
            $openItem = $this->openItems->findForAdministration($administrationId, $allocation->openItemId());
            if ($settlementAmount === null || $openItem === null) {
                return null;
            }
            $settlements[] = new BankTransactionSettlementResult($allocation->id(), $allocation->openItemId(), $settlementAmount, $openItem->openAmount());
        }

        return new BankTransactionPostingDetail($transaction, $posting, $settlements);
    }
}
