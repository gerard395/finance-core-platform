<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum DeliveryOutboxStatus: string
{
    case Available = 'available';
    case Processing = 'processing';
    case Processed = 'processed';
    case Blocked = 'blocked';
}
