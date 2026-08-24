<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;

interface TaxPostingReadRepository
{
    /** @return list<TaxPosting> */
    public function findOriginalsForSource(
        AdministrationId $administrationId,
        TaxSourceDocumentType $sourceDocumentType,
        TaxSourceDocumentId $sourceDocumentId,
    ): array;

    /** @return list<TaxPosting> */
    public function findForAdministrationAndPeriod(
        AdministrationId $administrationId,
        PostingDate $startDate,
        PostingDate $endDate,
    ): array;
}
