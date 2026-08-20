<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\LedgerAccountStore;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\LedgerAccountRecord;
use DomainException;

final class EloquentLedgerAccountRepository implements LedgerAccountReadRepository, LedgerAccountStore
{
    public function findForAdministration(AdministrationId $administrationId): array
    {
        return LedgerAccountRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->orderBy('code')
            ->orderBy('id')
            ->get()
            ->map(static fn (LedgerAccountRecord $record): LedgerAccount => new LedgerAccount(
                new LedgerAccountId(new Uuid($record->getAttribute('id'))),
                new LedgerAccountCode($record->getAttribute('code')),
                new LedgerAccountName($record->getAttribute('name')),
                LedgerAccountType::from($record->getAttribute('type')),
                LedgerAccountStatus::from($record->getAttribute('status')),
            ))
            ->all();
    }

    public function save(AdministrationId $administrationId, LedgerAccount $ledgerAccount): void
    {
        $existing = LedgerAccountRecord::query()->find($ledgerAccount->id()->toString());

        if ($existing !== null && $existing->getAttribute('administration_id') !== $administrationId->toString()) {
            throw new DomainException('A ledger account identity belongs to another Administration.');
        }

        LedgerAccountRecord::query()->updateOrCreate(
            ['id' => $ledgerAccount->id()->toString()],
            [
                'administration_id' => $administrationId->toString(),
                'code' => $ledgerAccount->code()->toString(),
                'name' => $ledgerAccount->name()->toString(),
                'type' => $ledgerAccount->type()->value,
                'status' => $ledgerAccount->status()->value,
            ],
        );
    }
}
