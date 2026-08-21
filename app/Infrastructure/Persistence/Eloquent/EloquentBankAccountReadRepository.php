<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Relations\BankAccountDetail;
use App\Application\Relations\BankAccountListItem;
use App\Application\Relations\BankAccountReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\BankAccountStatus;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\RelationBankAccountRecord;

final class EloquentBankAccountReadRepository implements BankAccountReadRepository
{
    public function listForRelation(AdministrationId $administrationId, RelationId $relationId): array
    {
        return RelationBankAccountRecord::query()->where('administration_id', $administrationId->toString())->where('relation_id', $relationId->toString())->orderBy('account_name')->orderBy('bank_account_id')->get()->map(fn (RelationBankAccountRecord $record): BankAccountListItem => $this->project($record, BankAccountListItem::class))->all();
    }

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId, BankAccountId $bankAccountId): ?BankAccountDetail
    {
        $record = RelationBankAccountRecord::query()->whereKey($bankAccountId->toString())->where('administration_id', $administrationId->toString())->where('relation_id', $relationId->toString())->first();

        return $record === null ? null : $this->project($record, BankAccountDetail::class);
    }

    /** @param class-string<BankAccountListItem> $class */
    private function project(RelationBankAccountRecord $record, string $class): BankAccountListItem
    {
        $bic = $record->getAttribute('bic');

        return new $class(new BankAccountId(new Uuid($record->getAttribute('bank_account_id'))), new Iban($record->getAttribute('iban')), $bic === null ? null : new Bic($bic), new AccountName($record->getAttribute('account_name')), (bool) $record->getAttribute('active') ? BankAccountStatus::Active : BankAccountStatus::Inactive);
    }
}
