<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\Enums\DeliveryOutcomeResolutionType;
use App\Domain\Sales\ValueObjects\DeliveryAttemptId;
use App\Domain\Sales\ValueObjects\DeliveryOutcomeResolutionId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DeliveryOutcomeResolution
{
    public function __construct(
        public DeliveryOutcomeResolutionId $id,
        public AdministrationId $administrationId,
        public DeliveryRequestId $requestId,
        public DeliveryAttemptId $attemptId,
        public DeliveryOutcomeResolutionType $type,
        public UserId $resolvedBy,
        public DateTimeImmutable $resolvedAt,
        public ?string $reason = null,
    ) {
        if ($reason !== null && ($reason !== trim($reason) || mb_strlen($reason) > 500 || str_contains($reason, "\0"))) {
            throw new InvalidArgumentException('Resolution reason must be plain trimmed text of at most 500 characters.');
        }
    }
}
