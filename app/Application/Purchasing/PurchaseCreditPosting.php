<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditPostingId;
use DateTimeImmutable;

final readonly class PurchaseCreditPosting
{
    public function __construct(public PurchaseCreditPostingId $id, public AdministrationId $administrationId, public PurchaseCreditInvoiceId $creditId, public JournalEntryId $journalEntryId, public OpenItemId $openItemId, public PostingDate $postingDate, public DateTimeImmutable $createdAt) {}
}
