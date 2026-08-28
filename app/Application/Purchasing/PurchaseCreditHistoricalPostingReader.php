<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;

interface PurchaseCreditHistoricalPostingReader
{
    public function readLocked(AdministrationId $admin, PurchaseCreditInvoice $credit): ?PurchaseCreditHistoricalPosting;
}
