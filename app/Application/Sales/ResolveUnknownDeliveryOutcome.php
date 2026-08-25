<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Identity\AuthorizationReadRepository;
use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Definitions\DeliveryOperationsPermission;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\Entities\DeliveryOutcomeResolution;
use App\Domain\Sales\Enums\DeliveryOutcomeResolutionType;
use App\Domain\Sales\ValueObjects\DeliveryAttemptId;
use App\Domain\Sales\ValueObjects\DeliveryOutcomeResolutionId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use DateTimeImmutable;

final readonly class ResolveUnknownDeliveryOutcome
{
    public function __construct(private AuthorizationReadRepository $authorization, private PermissionAuthorizer $authorizer, private DeliveryOutcomeResolutionStore $resolutions) {}

    public function execute(DeliveryOutcomeResolutionId $resolutionId, AdministrationId $administrationId, DeliveryRequestId $requestId, DeliveryAttemptId $attemptId, DeliveryOutcomeResolutionType $type, UserId $actor, ?string $reason = null): DeliveryOutcomeResolutionStatus
    {
        $now = new DateTimeImmutable;
        if (! $this->authorizer->allows($this->authorization->effectivePermissionIds($actor, $administrationId, $now), DeliveryOperationsPermission::ResolveUnknownOutcome->id())) {
            return DeliveryOutcomeResolutionStatus::Unauthorized;
        }

        return $this->resolutions->appendForUnknownAttempt(new DeliveryOutcomeResolution($resolutionId, $administrationId, $requestId, $attemptId, $type, $actor, $now, $reason));
    }
}
