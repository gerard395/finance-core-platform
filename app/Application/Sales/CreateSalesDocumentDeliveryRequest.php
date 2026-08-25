<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\Entities\DeliveryRequest;
use App\Domain\Sales\Enums\DeliveryRequestStatus;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use DateTimeImmutable;

final readonly class CreateSalesDocumentDeliveryRequest
{
    public function __construct(
        private DocumentArtifactRepository $artifacts,
        private ReadDocumentArtifact $artifactReader,
        private SalesDocumentDeliverySourceReader $sources,
        private SalesDocumentRecipientReader $recipients,
        private SalesDocumentSenderReader $senders,
        private DeliveryRequestStore $requests,
    ) {}

    public function execute(AdministrationId $administrationId, DeliveryRequestId $requestId, SalesDocumentSource $source, ArtifactId $artifactId, UserId $initiatedBy): CreateDeliveryRequestResult
    {
        $artifact = $this->artifacts->find($administrationId, $artifactId);
        $sourceView = $this->sources->read($administrationId, $source);
        if ($artifact === null || $sourceView === null || $artifact->documentType !== $source->type || (string) $artifact->sourceId !== $source->id) {
            return new CreateDeliveryRequestResult(CreateDeliveryRequestStatus::NotFound);
        }
        if (! $this->artifactReader->execute($administrationId, $artifactId)->integrityValid) {
            return new CreateDeliveryRequestResult(CreateDeliveryRequestStatus::InvalidArtifact);
        }
        $purpose = match ($source->type) {
            SalesDocumentType::Quotation => SalesDocumentRecipientPurpose::Quotation,
            SalesDocumentType::SalesInvoice => SalesDocumentRecipientPurpose::SalesInvoice,
            SalesDocumentType::SalesCreditInvoice => SalesDocumentRecipientPurpose::SalesCreditInvoice,
        };
        $recipient = $this->recipients->read($administrationId, $sourceView->relationId, $purpose);
        if ($recipient->status !== SalesDocumentRecipientStatus::Success || $recipient->emailAddress === null || $recipient->displayName === null) {
            return new CreateDeliveryRequestResult(CreateDeliveryRequestStatus::MissingRecipient);
        }
        $sender = $this->senders->readSender($administrationId);
        if ($sender->status !== SalesDocumentSenderStatus::Success || $sender->fromEmail === null || $sender->fromName === null) {
            return new CreateDeliveryRequestResult(CreateDeliveryRequestStatus::MissingSender);
        }
        [$subject, $body, $version] = $this->content($source->type, $sourceView->documentNumber, $sourceView->customerName, $sender->fromName);
        $semantic = [
            'source_type' => $source->type->value, 'source_id' => $source->id, 'artifact_id' => $artifactId->toString(),
            'recipient' => [$recipient->emailAddress->value(), $recipient->displayName->value(), $recipient->contactId?->toString(), 'preference'],
            'sender' => [$sender->fromName, $sender->fromEmail->value(), $sender->replyTo?->value()], 'subject' => $subject, 'body' => $body, 'template' => $version,
        ];
        $fingerprint = hash('sha256', json_encode($semantic, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $request = new DeliveryRequest($requestId, $administrationId, $source->type, $source->id, $artifactId, $recipient->emailAddress->value(), $recipient->displayName->value(), $recipient->contactId?->toString(), 'preference', $sender->fromName, $sender->fromEmail->value(), $sender->replyTo?->value(), $subject, $body, $version, $fingerprint, $initiatedBy, DeliveryRequestStatus::Prepared, new DateTimeImmutable);

        return $this->requests->createWithInitialOutbox($request);
    }

    /** @return array{string, string, string} */
    private function content(SalesDocumentType $type, string $number, string $recipient, string $issuer): array
    {
        return match ($type) {
            SalesDocumentType::Quotation => ["Offerte {$number}", "Beste {$recipient},\n\nIn de bijlage vindt u onze offerte {$number}.\n\nMet vriendelijke groet,\n{$issuer}", 'quotation-mail-v1'],
            SalesDocumentType::SalesInvoice => ["Factuur {$number}", "Beste {$recipient},\n\nIn de bijlage vindt u factuur {$number}.\n\nMet vriendelijke groet,\n{$issuer}", 'sales-invoice-mail-v1'],
            SalesDocumentType::SalesCreditInvoice => ["Creditfactuur {$number}", "Beste {$recipient},\n\nIn de bijlage vindt u creditfactuur {$number}.\n\nMet vriendelijke groet,\n{$issuer}", 'sales-credit-mail-v1'],
        };
    }
}
