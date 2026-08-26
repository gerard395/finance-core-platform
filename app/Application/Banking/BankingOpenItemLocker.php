<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface BankingOpenItemLocker
{
    /** @param list<OpenItemId> $ids @return list<OpenItem> */
    public function lock(AdministrationId $administrationId, array $ids): array;
}
