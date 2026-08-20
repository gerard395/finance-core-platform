<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

use App\Domain\Fiscal\Entities\TaxPosting;

interface TaxPostingStore
{
    public function append(TaxPosting $taxPosting): void;
}
