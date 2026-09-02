<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentGroupId;

interface TaxPostingReadRepository
{
    /** @return list<TaxPosting> */
    public function findOriginalsForSource(
        AdministrationId $administrationId,
        TaxSourceDocumentType $sourceDocumentType,
        TaxSourceDocumentId $sourceDocumentId,
    ): array;

    public function hasReversalForOriginalSource(
        AdministrationId $administrationId,
        TaxSourceDocumentType $sourceDocumentType,
        TaxSourceDocumentId $sourceDocumentId,
    ): bool;

    /** @return list<TaxPosting> */
    public function findForAdministrationAndPeriod(
        AdministrationId $administrationId,
        PostingDate $startDate,
        PostingDate $endDate,
    ): array;

    /** @return list<TaxPosting> */
    public function findForTreatmentGroup(
        AdministrationId $administrationId,
        TaxTreatmentGroupId $groupId,
    ): array;
}
