<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;

final readonly class PostPurchaseInvoiceResult
{
    private function __construct(public PostPurchaseInvoiceStatus $status, public ?JournalEntryId $journalEntryId = null, public ?OpenItemId $openItemId = null) {}

    public static function status(PostPurchaseInvoiceStatus $status): self
    {
        return new self($status);
    }

    public static function success(JournalEntryId $journalEntryId, OpenItemId $openItemId): self
    {
        return new self(PostPurchaseInvoiceStatus::Success, $journalEntryId, $openItemId);
    }
}
