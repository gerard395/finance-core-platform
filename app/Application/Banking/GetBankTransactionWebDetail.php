<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Relations\RelationReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class GetBankTransactionWebDetail
{
    public function __construct(private BankTransactionRepository $transactions, private AdministrationBankAccountRepository $banks, private RelationReadRepository $relations, private LedgerAccountReadRepository $accounts, private OpenItemReadRepository $openItems, private GetBankTransactionPostingDetail $postings, private GetBankTransactionReversalReadiness $reversals) {}

    public function execute(AdministrationId $administrationId, BankTransactionId $id): ?BankTransactionWebDetail
    {
        $transaction = $this->transactions->find($administrationId, $id);
        if ($transaction === null) {
            return null;
        }
        $bank = $this->banks->find($administrationId, $transaction->bankAccountId());
        if ($bank === null) {
            return null;
        }
        $payment = $transaction->paymentOrNull();
        $other = $transaction->otherIntentOrNull();
        $relation = $payment === null ? null : $this->relations->findByIdForAdministration($administrationId, $payment->relationId());
        $contraAccount = $other === null ? null : collect($this->accounts->findForAdministration($administrationId))->first(fn ($account) => $account->id()->equals($other->contraLedgerAccountId()));
        if (($payment !== null && $relation === null) || ($other !== null && $contraAccount === null)) {
            return null;
        }
        $items = [];
        foreach ($payment?->allocations() ?? [] as $allocation) {
            $item = $this->openItems->findForAdministration($administrationId, $allocation->openItemId());
            if ($item === null) {
                return null;
            }
            $items[$item->id()->toString()] = $item;
        }

        $reversal = $this->reversals->execute($administrationId, $id);
        if ($reversal === null) {
            return null;
        }

        return new BankTransactionWebDetail($transaction, $bank, $relation, $contraAccount, $items, $this->postings->execute($administrationId, $id), $reversal);
    }
}
