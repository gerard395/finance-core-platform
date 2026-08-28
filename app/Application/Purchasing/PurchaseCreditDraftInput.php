<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use DateTimeImmutable;

final readonly class PurchaseCreditDraftInput
{
    /** @param list<PurchaseInvoiceLineId> $selectedLineIds */
    public function __construct(public PurchaseInvoiceId $sourceInvoiceId, public PurchaseCreditInvoiceNumber $number, public DateTimeImmutable $creditDate, public DateTimeImmutable $receivedDate, public array $selectedLineIds) {}
}
