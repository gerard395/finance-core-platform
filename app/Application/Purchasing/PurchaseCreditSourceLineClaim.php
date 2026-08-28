<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceLineId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditSourceLineClaimId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use DateTimeImmutable;

final readonly class PurchaseCreditSourceLineClaim
{
    public function __construct(public PurchaseCreditSourceLineClaimId $id, public AdministrationId $administrationId, public PurchaseInvoiceLineId $sourceLineId, public PurchaseCreditInvoiceId $creditId, public PurchaseCreditInvoiceLineId $creditLineId, public DateTimeImmutable $createdAt) {}
}
