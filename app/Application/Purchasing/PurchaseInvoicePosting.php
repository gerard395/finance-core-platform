<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use DateTimeImmutable;

final readonly class PurchaseInvoicePosting
{
    public function __construct(public AdministrationId $administrationId, public PurchaseInvoiceId $purchaseInvoiceId, public JournalEntryId $journalEntryId, public OpenItemId $openItemId, public PostingDate $postingDate, public DateTimeImmutable $createdAt) {}
}
