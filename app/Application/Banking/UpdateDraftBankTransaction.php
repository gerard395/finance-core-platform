<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Relations\RelationReadRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Entities\PaymentAllocation;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;

final readonly class UpdateDraftBankTransaction
{
    public function __construct(private TransactionManager $transactions, private BankTransactionRepository $repository, private AdministrationBankAccountRepository $banks, private RelationReadRepository $relations) {}

    /** @param list<BankTransactionAllocationInput> $allocations */
    public function execute(AdministrationId $admin, BankTransactionId $id, AdministrationBankAccountId $bankId, TransactionDate $date, Money $amount, BankTransactionReference $reference, TransactionDescription $description, RelationId $relationId, array $allocations): BankTransactionResult
    {
        return $this->transactions->run(function () use ($admin, $id, $bankId, $date, $amount, $reference, $description, $relationId, $allocations) {
            $tx = $this->repository->find($admin, $id, true);
            if ($tx === null) {
                return BankTransactionResult::NotFound;
            }if ($tx->paymentOrNull() === null) {
                return BankTransactionResult::InvalidState;
            }$bank = $this->banks->find($admin, $bankId);
            if ($bank === null || ! $bank->isActive() || $this->relations->findByIdForAdministration($admin, $relationId) === null) {
                return BankTransactionResult::InvalidReference;
            }$payment = new Payment($tx->payment()->id(), $amount->isPositive() ? PaymentType::CustomerReceipt : PaymentType::SupplierPayment, $relationId, $amount->absolute(), array_map(static fn ($a) => new PaymentAllocation($a->id, $a->openItemId, $a->amount), $allocations));
            try {
                $tx->updateDraft($bankId, $date, $amount, $reference, $description, $payment);
            } catch (\DomainException) {
                return BankTransactionResult::InvalidState;
            }$this->repository->save($tx);

            return BankTransactionResult::Success;
        });
    }
}
