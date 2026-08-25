<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Enums\DeliveryAttemptResult;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\DeliveryAttemptId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use DateTimeImmutable;

final readonly class DeliveryAttempt
{
    public function __construct(
        public DeliveryAttemptId $id,
        public AdministrationId $administrationId,
        public DeliveryRequestId $requestId,
        public int $number,
        public ArtifactId $artifactId,
        public DeliveryAttemptResult $result,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $completedAt = null,
        public ?string $transport = null,
        public ?string $externalMessageId = null,
        public ?string $errorCategory = null,
        public bool $retryable = false,
    ) {}
}
