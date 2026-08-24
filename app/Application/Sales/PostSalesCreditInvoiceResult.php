<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;

final readonly class PostSalesCreditInvoiceResult
{
    /** @param list<TaxPostingId> $taxPostingIds */
    private function __construct(
        private PostSalesCreditInvoiceStatus $status,
        private ?JournalEntryId $journalEntryId = null,
        private ?OpenItemId $openItemId = null,
        private array $taxPostingIds = [],
    ) {}

    /** @param list<TaxPostingId> $taxPostingIds */
    public static function success(JournalEntryId $journalEntryId, OpenItemId $openItemId, array $taxPostingIds): self
    {
        return new self(PostSalesCreditInvoiceStatus::Success, $journalEntryId, $openItemId, array_values($taxPostingIds));
    }

    public static function forStatus(PostSalesCreditInvoiceStatus $status): self
    {
        return new self($status);
    }

    public function status(): PostSalesCreditInvoiceStatus
    {
        return $this->status;
    }

    public function journalEntryId(): ?JournalEntryId
    {
        return $this->journalEntryId;
    }

    public function openItemId(): ?OpenItemId
    {
        return $this->openItemId;
    }

    /** @return list<TaxPostingId> */
    public function taxPostingIds(): array
    {
        return $this->taxPostingIds;
    }
}
