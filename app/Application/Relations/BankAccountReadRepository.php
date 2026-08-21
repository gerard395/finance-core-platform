<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\RelationId;

interface BankAccountReadRepository
{
    /** @return list<BankAccountListItem> */
    public function listForRelation(AdministrationId $administrationId, RelationId $relationId): array;

    public function findForRelation(AdministrationId $administrationId, RelationId $relationId, BankAccountId $bankAccountId): ?BankAccountDetail;
}
