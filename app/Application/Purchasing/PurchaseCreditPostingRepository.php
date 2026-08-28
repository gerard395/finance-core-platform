<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;

interface PurchaseCreditPostingRepository
{
    public function find(AdministrationId $admin, PurchaseCreditInvoiceId $id): ?PurchaseCreditPosting;

    public function findReadModel(AdministrationId $admin, PurchaseCreditInvoiceId $id): ?PurchaseCreditPostingReadModel;

    /** @param list<PurchaseCreditSourceLineClaim> $claims */
    public function appendClaims(array $claims): bool;

    public function append(PurchaseCreditPosting $posting): bool;
}
