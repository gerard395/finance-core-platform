<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Relations\RelationReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class GetBankTransactionWebDetail
{
    public function __construct(private BankTransactionRepository $transactions, private AdministrationBankAccountRepository $banks, private RelationReadRepository $relations, private OpenItemReadRepository $openItems, private GetBankTransactionPostingDetail $postings) {}

    public function execute(AdministrationId $administrationId, BankTransactionId $id): ?BankTransactionWebDetail
    {
        $transaction = $this->transactions->find($administrationId, $id);
        if ($transaction === null) {
            return null;
        }
        $bank = $this->banks->find($administrationId, $transaction->bankAccountId());
        $relation = $this->relations->findByIdForAdministration($administrationId, $transaction->payment()->relationId());
        if ($bank === null || $relation === null) {
            return null;
        }
        $items = [];
        foreach ($transaction->payment()->allocations() as $allocation) {
            $item = $this->openItems->findForAdministration($administrationId, $allocation->openItemId());
            if ($item === null) {
                return null;
            }
            $items[$item->id()->toString()] = $item;
        }

        return new BankTransactionWebDetail($transaction, $bank, $relation, $items, $this->postings->execute($administrationId, $id));
    }
}
