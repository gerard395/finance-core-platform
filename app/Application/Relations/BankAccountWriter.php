<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\BankAccountId;

interface BankAccountWriter
{
    public function create(AdministrationId $administrationId, Relation $relation, BankAccountId $bankAccountId): BankAccountWriteResult;

    public function update(AdministrationId $administrationId, Relation $relation, BankAccountId $bankAccountId): BankAccountWriteResult;
}
