<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\Enums\DeliveryRequestStatus;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use DateTimeImmutable;

final readonly class DeliveryRequest
{
    public function __construct(
        public DeliveryRequestId $id,
        public AdministrationId $administrationId,
        public SalesDocumentType $documentType,
        public string $sourceId,
        public ArtifactId $artifactId,
        public string $recipientEmail,
        public string $recipientName,
        public ?string $recipientContactId,
        public string $recipientSource,
        public string $fromName,
        public string $fromEmail,
        public ?string $replyTo,
        public string $subject,
        public string $body,
        public string $templateVersion,
        public string $semanticFingerprint,
        public UserId $initiatedBy,
        public DeliveryRequestStatus $status,
        public DateTimeImmutable $createdAt,
    ) {}
}
