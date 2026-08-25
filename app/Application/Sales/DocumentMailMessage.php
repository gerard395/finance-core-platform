<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class DocumentMailMessage
{
    public function __construct(
        public string $toEmail,
        public string $toName,
        public string $fromEmail,
        public string $fromName,
        public ?string $replyTo,
        public string $subject,
        public string $body,
        public string $attachmentBytes,
        public string $attachmentFilename,
        public string $mimeType = 'application/pdf',
    ) {}
}
