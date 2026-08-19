<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Services;

use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use DomainException;

final readonly class TaxPostingIdentityPolicy
{
    /** @param list<TaxPosting> $history */
    public function assertNewIdAvailable(TaxPostingId $id, array $history): void
    {
        foreach ($history as $posting) {
            if ($posting->id()->equals($id)) {
                throw new DomainException('Tax posting identity must be unique within fiscal history.');
            }
        }
    }
}
