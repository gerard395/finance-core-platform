<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class SalesCreditInvoiceListItem
{
    public function __construct(public SalesCreditInvoiceId $id, public SalesCreditInvoiceNumber $number, public SalesInvoiceId $sourceInvoiceId, public SalesInvoiceNumber $sourceInvoiceNumber, public DisplayName $customerName, public DateTimeImmutable $creditDate, public SalesCreditInvoiceStatus $status, public Currency $currency, public Money $total) {}
}
