<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class UpdateBankAccount
{
    public function __construct(private RelationReadRepository $relations, private BankAccountWriter $bankAccounts, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, BankAccountId $bankAccountId, AccountName $accountName): BankAccountWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $relationId, $bankAccountId, $accountName): BankAccountWriteResult {
            $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);
            $bankAccount = $relation?->bankAccount($bankAccountId);
            if ($bankAccount === null) {
                return BankAccountWriteResult::NotFound;
            }
            $bankAccount->rename($accountName);

            return $this->bankAccounts->update($administrationId, $relation, $bankAccountId);
        });
    }
}
