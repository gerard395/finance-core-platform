<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Enums\SalesDocumentType;

final readonly class AssessSalesDocumentDeliveryReadiness
{
    public function __construct(
        private SalesDocumentDeliverySourceReader $sources,
        private SalesDocumentRecipientReader $recipients,
        private SalesDocumentIssuerReadiness $issuers,
        private SalesDocumentSenderReader $senders,
        private SalesDocumentDeliveryInfrastructureReadiness $infrastructure,
        private SalesDocumentDeliveryHistoryReader $history,
    ) {}

    public function execute(AdministrationId $administrationId, SalesDocumentSource $source, bool $resend, ?DeliveryRecipientOverride $recipientOverride = null): SalesDocumentDeliveryReadiness
    {
        $history = $this->history->history($administrationId, $source);
        $view = $this->sources->read($administrationId, $source);
        if ($view === null) {
            return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::NotFound, $history);
        }
        if (! $this->eligible($source->type, $view->status, $resend, $history)) {
            return new SalesDocumentDeliveryReadiness($resend && $history->requests === [] ? SalesDocumentDeliveryReadinessStatus::ResendNotApplicable : SalesDocumentDeliveryReadinessStatus::IneligibleStatus, $history);
        }
        if ($this->hasBlockingUnknown($history, $view->status)) {
            return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::OutcomeUnknown, $history);
        }
        if ($source->type === SalesDocumentType::Quotation && ! $view->hasDocumentAddress) {
            return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::MissingDocumentAddress, $history);
        }
        $purpose = self::purpose($source->type);
        if ($recipientOverride === null && $this->recipients->read($administrationId, $view->relationId, $purpose)->status !== SalesDocumentRecipientStatus::Success) {
            return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::MissingRecipient, $history);
        }
        if ($this->issuers->assess($purpose, $administrationId) !== SalesDocumentIssuerReadinessStatus::Success) {
            return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::MissingIssuer, $history);
        }
        if ($this->senders->readSender($administrationId)->status !== SalesDocumentSenderStatus::Success) {
            return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::MissingSender, $history);
        }
        if ($this->infrastructure->check()->status !== DeliveryInfrastructureReadinessStatus::Ready) {
            return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::InfrastructureUnavailable, $history);
        }

        return new SalesDocumentDeliveryReadiness(SalesDocumentDeliveryReadinessStatus::Ready, $history);
    }

    private function eligible(SalesDocumentType $type, string $status, bool $resend, SalesDocumentDeliveryHistory $history): bool
    {
        if ($resend && $history->requests === []) {
            return false;
        }

        return match ($type) {
            SalesDocumentType::Quotation => $resend ? in_array($status, ['draft', 'sent'], true) : $status === 'draft',
            SalesDocumentType::SalesInvoice => in_array($status, ['finalized', 'posted', 'paid'], true),
            SalesDocumentType::SalesCreditInvoice => in_array($status, ['finalized', 'posted'], true),
        };
    }

    private function hasBlockingUnknown(SalesDocumentDeliveryHistory $history, string $sourceStatus): bool
    {
        foreach ($history->attempts as $attempt) {
            if ($attempt['result'] !== 'outcome_unknown') {
                continue;
            }
            $resolution = $history->resolutions[$attempt['id']] ?? null;
            if ($resolution === null || ($resolution['resolution_type'] === 'handled_externally' && $sourceStatus === 'draft')) {
                return true;
            }
        }

        return false;
    }

    public static function purpose(SalesDocumentType $type): SalesDocumentRecipientPurpose
    {
        return match ($type) {
            SalesDocumentType::Quotation => SalesDocumentRecipientPurpose::Quotation,
            SalesDocumentType::SalesInvoice => SalesDocumentRecipientPurpose::SalesInvoice,
            SalesDocumentType::SalesCreditInvoice => SalesDocumentRecipientPurpose::SalesCreditInvoice,
        };
    }
}
