<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\BankAccount;
use App\Domain\Relations\Enums\BankAccountStatus;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\RelationId;

final readonly class CreateBankAccount
{
    public function __construct(private RelationReadRepository $relations, private BankAccountWriter $bankAccounts, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, RelationId $relationId, BankAccountId $bankAccountId, Iban $iban, ?Bic $bic, AccountName $accountName): BankAccountWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $relationId, $bankAccountId, $iban, $bic, $accountName): BankAccountWriteResult {
            $relation = $this->relations->findByIdForAdministration($administrationId, $relationId);
            if ($relation === null) {
                return BankAccountWriteResult::NotFound;
            }
            if ($relation->hasBankAccount($bankAccountId)) {
                return BankAccountWriteResult::DuplicateIdentity;
            }
            $relation->addBankAccount(new BankAccount($bankAccountId, $iban, $bic, $accountName, BankAccountStatus::Active));

            return $this->bankAccounts->create($administrationId, $relation, $bankAccountId);
        });
    }
}
