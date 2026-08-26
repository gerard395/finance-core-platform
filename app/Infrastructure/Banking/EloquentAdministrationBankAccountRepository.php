<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\AdministrationBankAccountRepository;
use App\Application\Banking\AdministrationBankAccountWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\AdministrationBankAccount;
use App\Domain\Banking\Enums\AdministrationBankAccountStatus;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankAccountLabel;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationBankAccountRecord;
use Illuminate\Database\QueryException;

final class EloquentAdministrationBankAccountRepository implements AdministrationBankAccountRepository
{
    public function findForAdministration(AdministrationId $administrationId): array
    {
        return AdministrationBankAccountRecord::query()->where('administration_id', $administrationId->toString())->orderBy('label')->orderBy('id')->get()->map(fn ($record) => $this->hydrate($record))->all();
    }

    public function find(AdministrationId $administrationId, AdministrationBankAccountId $id): ?AdministrationBankAccount
    {
        $record = AdministrationBankAccountRecord::query()->where('administration_id', $administrationId->toString())->whereKey($id->toString())->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function save(AdministrationBankAccount $account): AdministrationBankAccountWriteResult
    {
        $attributes = ['id' => $account->id()->toString(), 'administration_id' => $account->administrationId()->toString(), 'iban' => $account->iban()->value(), 'bic' => $account->bic()?->value(), 'account_holder' => $account->accountHolder()->value(), 'label' => $account->label()->value(), 'currency' => $account->currency()->code(), 'status' => $account->status()->value];
        $existing = AdministrationBankAccountRecord::query()->find($account->id()->toString());
        if ($existing !== null && $existing->getAttribute('administration_id') !== $account->administrationId()->toString()) {
            return AdministrationBankAccountWriteResult::DuplicateIdentity;
        }
        try {
            AdministrationBankAccountRecord::query()->updateOrCreate(['id' => $account->id()->toString(), 'administration_id' => $account->administrationId()->toString()], $attributes);
        } catch (QueryException $exception) {
            if (AdministrationBankAccountRecord::query()->where('administration_id', $account->administrationId()->toString())->where('iban', $account->iban()->value())->exists()) {
                return AdministrationBankAccountWriteResult::DuplicateIban;
            }
            throw $exception;
        }

        return AdministrationBankAccountWriteResult::Success;
    }

    private function hydrate(AdministrationBankAccountRecord $record): AdministrationBankAccount
    {
        return new AdministrationBankAccount(new AdministrationBankAccountId(new Uuid($record->getAttribute('id'))), new AdministrationId(new Uuid($record->getAttribute('administration_id'))), new Iban($record->getAttribute('iban')), ($bic = $record->getAttribute('bic')) === null ? null : new Bic($bic), new AccountName($record->getAttribute('account_holder')), new BankAccountLabel($record->getAttribute('label')), new Currency($record->getAttribute('currency')), AdministrationBankAccountStatus::from($record->getAttribute('status')));
    }
}
