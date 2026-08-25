<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;

final readonly class PurchaseInvoiceDraftInput
{
    /** @param list<PurchaseInvoiceLineInput> $lines */
    public function __construct(public SupplierId $supplierId, public SupplierInvoiceNumber $number, public DateTimeImmutable $invoiceDate, public DateTimeImmutable $receivedDate, public ?DateTimeImmutable $supplyDate, public DateTimeImmutable $dueDate, public Currency $currency, public PurchaseDocumentAddress $address, public array $lines) {}
}
