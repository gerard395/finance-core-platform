<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum SalesDocumentDeliveryReadinessStatus: string
{
    case Ready = 'ready';
    case NotFound = 'not_found';
    case IneligibleStatus = 'ineligible_status';
    case MissingDocumentAddress = 'missing_document_address';
    case MissingRecipient = 'missing_recipient';
    case MissingIssuer = 'missing_issuer';
    case MissingSender = 'missing_sender';
    case InfrastructureUnavailable = 'infrastructure_unavailable';
    case OutcomeUnknown = 'outcome_unknown';
    case ResendNotApplicable = 'resend_not_applicable';
}
