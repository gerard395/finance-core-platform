<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;

final readonly class QueueSalesDocumentDelivery
{
    public function __construct(
        private AssessSalesDocumentDeliveryReadiness $readiness,
        private PrepareSalesDocumentArtifact $artifacts,
        private CreateSalesDocumentDeliveryRequest $requests,
    ) {}

    public function execute(AdministrationId $administrationId, DeliveryRequestId $requestId, SalesDocumentSource $source, UserId $actor, bool $resend, ?DeliveryRecipientOverride $recipientOverride = null): QueueSalesDocumentDeliveryResult
    {
        $readiness = $this->readiness->execute($administrationId, $source, $resend, $recipientOverride);
        if (! $readiness->ready()) {
            return new QueueSalesDocumentDeliveryResult($readiness->status);
        }
        $prepared = $this->artifacts->execute($administrationId, $source);
        if ($prepared->artifact === null) {
            return new QueueSalesDocumentDeliveryResult(match ($prepared->status) {
                PrepareSalesDocumentArtifactStatus::MissingDocumentAddress => SalesDocumentDeliveryReadinessStatus::MissingDocumentAddress,
                PrepareSalesDocumentArtifactStatus::MissingIssuerData, PrepareSalesDocumentArtifactStatus::MissingPaymentData => SalesDocumentDeliveryReadinessStatus::MissingIssuer,
                default => SalesDocumentDeliveryReadinessStatus::InfrastructureUnavailable,
            });
        }
        $created = $this->requests->execute($administrationId, $requestId, $source, $prepared->artifact->id, $actor, $recipientOverride);

        return new QueueSalesDocumentDeliveryResult(
            match ($created->status) {
                CreateDeliveryRequestStatus::Created, CreateDeliveryRequestStatus::Existing => SalesDocumentDeliveryReadinessStatus::Ready,
                CreateDeliveryRequestStatus::MissingRecipient => SalesDocumentDeliveryReadinessStatus::MissingRecipient,
                CreateDeliveryRequestStatus::MissingSender => SalesDocumentDeliveryReadinessStatus::MissingSender,
                default => SalesDocumentDeliveryReadinessStatus::InfrastructureUnavailable,
            },
            $created->status === CreateDeliveryRequestStatus::Existing,
        );
    }
}
