<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;

final readonly class SalesDocumentRecipient
{
    public function __construct(
        public SalesDocumentRecipientStatus $status,
        public ?ContactId $contactId = null,
        public ?ContactName $displayName = null,
        public ?EmailAddress $emailAddress = null,
    ) {}
}
