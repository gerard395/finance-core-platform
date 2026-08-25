<?php

declare(strict_types=1);

namespace App\Presentation\Sales;

use App\Application\Sales\SalesDocumentDeliveryReadinessStatus;

final class SalesDocumentDeliveryPresenter
{
    public static function readiness(SalesDocumentDeliveryReadinessStatus $status): string
    {
        return match ($status) {
            SalesDocumentDeliveryReadinessStatus::Ready => 'Documentverzending is gereed.',
            SalesDocumentDeliveryReadinessStatus::MissingRecipient => 'Stel eerst een ontvanger voor dit documenttype in bij de relatie.',
            SalesDocumentDeliveryReadinessStatus::MissingIssuer => 'Vul eerst de documentgegevens van de administratie in.',
            SalesDocumentDeliveryReadinessStatus::MissingSender => 'Vul eerst de e-mailafzender van de administratie in.',
            SalesDocumentDeliveryReadinessStatus::MissingDocumentAddress => 'Deze oudere offerte heeft geen vastgelegd offerteadres.',
            SalesDocumentDeliveryReadinessStatus::OutcomeUnknown => 'Verzendstatus onzeker. Neem contact op met een bevoegde beheerder.',
            SalesDocumentDeliveryReadinessStatus::IneligibleStatus, SalesDocumentDeliveryReadinessStatus::ResendNotApplicable => 'Dit document kan in de huidige status niet worden verzonden.',
            default => 'Documentverzending is tijdelijk niet beschikbaar.',
        };
    }

    public static function status(string $status): string
    {
        return match ($status) {
            'requested' => 'In wachtrij', 'prepared' => 'Voorbereid', 'attempting' => 'Wordt verzonden',
            'accepted_by_transport' => 'Geaccepteerd door mailserver', 'failed' => 'Verzenden mislukt',
            'outcome_unknown' => 'Verzendstatus onzeker', 'handled_externally' => 'Handmatig afgehandeld',
            'authorize_resend' => 'Opnieuw verzenden toegestaan', 'failed_transport', 'failed_validation' => 'Verzenden mislukt',
            default => 'Onbekend',
        };
    }
}
