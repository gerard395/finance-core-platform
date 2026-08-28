<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;

interface PurchaseCreditInvoiceRepository
{
    public function create(PurchaseCreditInvoice $credit): bool;

    public function save(PurchaseCreditInvoice $credit): bool;

    public function find(AdministrationId $admin, PurchaseCreditInvoiceId $id): ?PurchaseCreditInvoice;

    public function findForUpdate(AdministrationId $admin, PurchaseCreditInvoiceId $id): ?PurchaseCreditInvoice;

    /** @return list<PurchaseCreditInvoice> */
    public function list(AdministrationId $admin): array;
}
