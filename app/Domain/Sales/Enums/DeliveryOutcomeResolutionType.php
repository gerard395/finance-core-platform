<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum DeliveryOutcomeResolutionType: string
{
    case HandledExternally = 'handled_externally';
    case AuthorizeResend = 'authorize_resend';
}
