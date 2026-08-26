<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\AdministrationBankAccount;
use App\Domain\Banking\Enums\AdministrationBankAccountStatus;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankAccountLabel;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Shared\Finance\Currency;

final readonly class ManageAdministrationBankAccounts
{
    public function __construct(private TransactionManager $transactions, private AdministrationBankAccountRepository $accounts, private AdministrationBankAccountIdentityGenerator $identities) {}

    /** @return array{AdministrationBankAccountWriteResult, AdministrationBankAccountId} */
    public function create(AdministrationId $administrationId, Iban $iban, ?Bic $bic, AccountName $holder, BankAccountLabel $label): array
    {
        $id = $this->identities->next();
        $result = $this->transactions->run(fn (): AdministrationBankAccountWriteResult => $this->accounts->save(new AdministrationBankAccount($id, $administrationId, $iban, $bic, $holder, $label, new Currency('EUR'), AdministrationBankAccountStatus::Active)));

        return [$result, $id];
    }

    public function update(AdministrationId $administrationId, AdministrationBankAccountId $id, AccountName $holder, BankAccountLabel $label): AdministrationBankAccountWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $id, $holder, $label): AdministrationBankAccountWriteResult {
            $account = $this->accounts->find($administrationId, $id);
            if ($account === null) {
                return AdministrationBankAccountWriteResult::NotFound;
            }
            $account->rename($holder, $label);

            return $this->accounts->save($account);
        });
    }

    public function setActive(AdministrationId $administrationId, AdministrationBankAccountId $id, bool $active): AdministrationBankAccountWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $id, $active): AdministrationBankAccountWriteResult {
            $account = $this->accounts->find($administrationId, $id);
            if ($account === null) {
                return AdministrationBankAccountWriteResult::NotFound;
            }
            $active ? $account->activate() : $account->deactivate();

            return $this->accounts->save($account);
        });
    }
}
