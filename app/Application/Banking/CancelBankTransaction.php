<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class CancelBankTransaction
{
    public function __construct(private TransactionManager $transactions, private BankTransactionRepository $repository) {}

    public function execute(AdministrationId $admin, BankTransactionId $id): BankTransactionResult
    {
        return $this->transactions->run(function () use ($admin, $id) {
            $tx = $this->repository->find($admin, $id, true);
            if ($tx === null) {
                return BankTransactionResult::NotFound;
            }try {
                $tx->cancel();
            } catch (\DomainException) {
                return BankTransactionResult::InvalidState;
            }$this->repository->save($tx);

            return BankTransactionResult::Success;
        });
    }
}
