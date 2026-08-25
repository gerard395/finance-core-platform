<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

interface PurchaseInvoiceRepository
{
    public function create(PurchaseInvoice $invoice): bool;

    public function save(PurchaseInvoice $invoice): bool;

    public function find(AdministrationId $administrationId, PurchaseInvoiceId $id): ?PurchaseInvoice;

    public function findForUpdate(AdministrationId $administrationId, PurchaseInvoiceId $id): ?PurchaseInvoice;

    /** @return list<PurchaseInvoice> */
    public function list(AdministrationId $administrationId): array;
}
