<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Relations\RelationReadRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Entities\PaymentAllocation;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Money;

final readonly class CreateManualBankTransaction
{
    public function __construct(private TransactionManager $transactions, private BankTransactionRepository $repository, private BankTransactionIdentityGenerator $ids, private BankTransactionClock $clock, private AdministrationBankAccountRepository $bankAccounts, private RelationReadRepository $relations) {}

    /** @param list<BankTransactionAllocationInput> $allocations @return array{BankTransactionResult, ?BankTransactionId} */
    public function execute(AdministrationId $admin, AdministrationBankAccountId $bankId, TransactionDate $date, Money $amount, BankTransactionReference $reference, TransactionDescription $description, RelationId $relationId, UserId $actor, array $allocations = []): array
    {
        $bank = $this->bankAccounts->find($admin, $bankId);
        if ($bank === null) {
            return [BankTransactionResult::NotFound, null];
        } if (! $bank->isActive() || $bank->currency()->code() !== 'EUR' || $this->relations->findByIdForAdministration($admin, $relationId) === null) {
            return [BankTransactionResult::InvalidReference, null];
        }
        $id = $this->ids->transaction();
        try {
            $payment = new Payment($this->ids->payment(), $amount->isPositive() ? PaymentType::CustomerReceipt : PaymentType::SupplierPayment, $relationId, $amount->absolute(), array_map(static fn ($a) => new PaymentAllocation($a->id, $a->openItemId, $a->amount), $allocations));
            $aggregate = new BankTransaction($id, $bankId, $admin, $date, $amount, $reference, $description, $payment, BankTransactionStatus::Draft, $actor, $this->clock->now());
        } catch (\DomainException) {
            return [BankTransactionResult::InvalidAllocation, null];
        }
        $this->transactions->run(fn () => $this->repository->save($aggregate));

        return [BankTransactionResult::Success, $id];
    }
}
