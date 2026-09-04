<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Identity\ValueObjects\UserId;

final readonly class FinalizeBankTransaction
{
    public function __construct(private TransactionManager $transactions, private BankTransactionRepository $repository, private BankTransactionClock $clock, private AdministrationBankAccountRepository $banks, private OpenItemReadRepository $openItems) {}

    public function execute(AdministrationId $admin, BankTransactionId $id, UserId $actor): BankTransactionResult
    {
        return $this->transactions->run(function () use ($admin, $id, $actor) {
            $tx = $this->repository->find($admin, $id, true);
            if ($tx === null) {
                return BankTransactionResult::NotFound;
            } if ($tx->paymentOrNull() === null) {
                return BankTransactionResult::InvalidState;
            } if ($tx->status() === BankTransactionStatus::Finalized) {
                return BankTransactionResult::AlreadyFinalized;
            } if ($tx->status() !== BankTransactionStatus::Draft) {
                return BankTransactionResult::InvalidState;
            } $bank = $this->banks->find($admin, $tx->bankAccountId());
            if ($bank === null || ! $bank->isActive()) {
                return BankTransactionResult::InvalidReference;
            } $items = [];
            foreach ($this->openItems->findForAdministrationAsOf($admin, new PostingDate($this->clock->now())) as $item) {
                $items[$item->id()->toString()] = $item;
            } $snapshots = [];
            $expectedType = $tx->payment()->type() === PaymentType::CustomerReceipt ? OpenItemType::Receivable : OpenItemType::Payable;
            $expectedSide = $tx->payment()->type() === PaymentType::CustomerReceipt ? OpenItemSide::Debit : OpenItemSide::Credit;
            foreach ($tx->payment()->allocations() as $allocation) {
                $item = $items[$allocation->openItemId()->toString()] ?? null;
                if ($item === null || $item->type() !== $expectedType || $item->side() !== $expectedSide || ! $item->relationId()->equals($tx->payment()->relationId()) || $item->isClosed() || ! $item->originalAmount()->currency()->equals($allocation->amount()->currency()) || $item->openAmount()->subtract($allocation->amount())->isNegative()) {
                    return BankTransactionResult::InvalidAllocation;
                } $snapshots[] = $allocation->finalized($item->type(), $item->side(), $item->relationId(), $item->controlLedgerAccountId());
            } try {
                $tx->finalize($actor, $this->clock->now(), $snapshots);
            } catch (\DomainException) {
                return BankTransactionResult::InvalidAllocation;
            } $this->repository->save($tx);

            return BankTransactionResult::Success;
        });
    }
}
