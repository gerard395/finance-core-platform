<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\BankAccountWriter;
use App\Application\Relations\BankAccountWriteResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\BankAccount;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Infrastructure\Persistence\Eloquent\Models\RelationBankAccountRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use Illuminate\Database\QueryException;

final class EloquentBankAccountWriter implements BankAccountWriter
{
    public function create(AdministrationId $administrationId, Relation $relation, BankAccountId $bankAccountId): BankAccountWriteResult
    {
        $bankAccount = $this->validated($administrationId, $relation, $bankAccountId);
        if ($bankAccount === null) {
            return BankAccountWriteResult::NotFound;
        }
        try {
            RelationBankAccountRecord::query()->create($this->attributes($administrationId, $relation, $bankAccount));
        } catch (QueryException $exception) {
            if (RelationBankAccountRecord::query()->whereKey($bankAccountId->toString())->exists()) {
                return BankAccountWriteResult::DuplicateIdentity;
            } throw $exception;
        }

        return BankAccountWriteResult::Success;
    }

    public function update(AdministrationId $administrationId, Relation $relation, BankAccountId $bankAccountId): BankAccountWriteResult
    {
        $bankAccount = $this->validated($administrationId, $relation, $bankAccountId);
        if ($bankAccount === null) {
            return BankAccountWriteResult::NotFound;
        }
        $record = RelationBankAccountRecord::query()->whereKey($bankAccountId->toString())->where('administration_id', $administrationId->toString())->where('relation_id', $relation->id()->toString())->first();
        if ($record === null) {
            return BankAccountWriteResult::NotFound;
        }
        $record->fill($this->attributes($administrationId, $relation, $bankAccount));
        $record->save();

        return BankAccountWriteResult::Success;
    }

    private function validated(AdministrationId $administrationId, Relation $relation, BankAccountId $id): ?BankAccount
    {
        if (! RelationRecord::query()->whereKey($relation->id()->toString())->where('administration_id', $administrationId->toString())->exists()) {
            return null;
        }

        return $relation->bankAccount($id);
    }

    /** @return array<string, string|bool|null> */
    private function attributes(AdministrationId $administrationId, Relation $relation, BankAccount $bankAccount): array
    {
        return ['bank_account_id' => $bankAccount->id()->toString(), 'administration_id' => $administrationId->toString(), 'relation_id' => $relation->id()->toString(), 'iban' => $bankAccount->iban()->value(), 'bic' => $bankAccount->bic()?->value(), 'account_name' => $bankAccount->accountName()->value(), 'active' => $bankAccount->isActive()];
    }
}
