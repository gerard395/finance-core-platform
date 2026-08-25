<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;

final readonly class DeliveryRecipientOverride
{
    public function __construct(public EmailAddress $email, public ?ContactName $displayName = null) {}
}
