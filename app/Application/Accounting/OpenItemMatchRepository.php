<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\OpenItemMatch;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface OpenItemMatchRepository
{
    public function findLockedPair(AdministrationId $administrationId, OpenItemId $debitOpenItemId, OpenItemId $creditOpenItemId): ?OpenItemMatchPair;

    public function appendMatch(OpenItemMatch $match): OpenItemMatchAppendResult;
}
