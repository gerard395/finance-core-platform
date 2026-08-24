<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;

final readonly class SalesInvoicePosting
{
    public function __construct(
        private AdministrationId $administrationId,
        private SalesInvoiceId $salesInvoiceId,
        private JournalEntryId $journalEntryId,
        private OpenItemId $openItemId,
        private DateTimeImmutable $createdAt,
    ) {}

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function salesInvoiceId(): SalesInvoiceId
    {
        return $this->salesInvoiceId;
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
