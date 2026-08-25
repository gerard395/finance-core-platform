<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\EmailAddress;

final readonly class SalesDocumentSender
{
    public function __construct(
        public SalesDocumentSenderStatus $status,
        public ?string $fromName = null,
        public ?EmailAddress $fromEmail = null,
        public ?EmailAddress $replyTo = null,
    ) {}
}
