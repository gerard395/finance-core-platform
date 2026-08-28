<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use LogicException;

final readonly class PurchaseCreditInvoiceListItem
{
    public function __construct(public PurchaseCreditInvoiceId $id, public string $supplierName, public PurchaseCreditInvoiceNumber $number, public DateTimeImmutable $creditDate, public DateTimeImmutable $receivedDate, public Money $gross, public PurchaseCreditInvoiceStatus $status, public PurchaseInvoiceId $sourceInvoiceId) {}

    public static function from(PurchaseCreditInvoice $credit): self
    {
        $source = $credit->sourcePurchaseInvoiceId();
        if ($source === null) {
            throw new LogicException('Persisted PurchaseCredit requires a source PurchaseInvoice.');
        }

        return new self($credit->id(), $credit->supplierSnapshot()?->name->toString() ?? '', $credit->number(), $credit->supplierCreditDate(), $credit->receivedDate(), $credit->grossTotal(), $credit->status(), $source);
    }
}
