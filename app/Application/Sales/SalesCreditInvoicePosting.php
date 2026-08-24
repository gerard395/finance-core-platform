<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use DateTimeImmutable;

final readonly class SalesCreditInvoicePosting
{
    public function __construct(
        private AdministrationId $administrationId,
        private SalesCreditInvoiceId $salesCreditInvoiceId,
        private JournalEntryId $journalEntryId,
        private OpenItemId $openItemId,
        private DateTimeImmutable $createdAt,
    ) {}

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function salesCreditInvoiceId(): SalesCreditInvoiceId
    {
        return $this->salesCreditInvoiceId;
    }

    public function journalEntryId(): JournalEntryId
    {
        return $this->journalEntryId;
    }

    public function openItemId(): OpenItemId
    {
        return $this->openItemId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
