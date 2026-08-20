<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface OpenItemReadRepository
{
    /** @return list<OpenItem> */
    public function findForAdministrationAsOf(
        AdministrationId $administrationId,
        PostingDate $asOfDate,
    ): array;
}
