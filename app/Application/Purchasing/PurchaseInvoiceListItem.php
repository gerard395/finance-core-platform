<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class PurchaseInvoiceListItem
{
    public function __construct(public PurchaseInvoiceId $id, public DisplayName $supplierName, public SupplierInvoiceNumber $number, public DateTimeImmutable $invoiceDate, public DateTimeImmutable $receivedDate, public DateTimeImmutable $dueDate, public Currency $currency, public Money $net, public Money $tax, public Money $gross, public PurchaseInvoiceStatus $status) {}

    public static function from(PurchaseInvoice $i): self
    {
        return new self($i->id(), $i->supplierSnapshot()->name, $i->supplierInvoiceNumber(), $i->supplierInvoiceDate(), $i->receivedDate(), $i->dueDate(), $i->currency(), $i->netTotal(), $i->taxTotal(), $i->grossTotal(), $i->status());
    }
}
