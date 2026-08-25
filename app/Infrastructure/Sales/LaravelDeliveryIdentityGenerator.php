<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\DeliveryIdentityGenerator;
use App\Domain\Sales\ValueObjects\DeliveryAttemptId;
use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelDeliveryIdentityGenerator implements DeliveryIdentityGenerator
{
    public function attemptId(): DeliveryAttemptId
    {
        return new DeliveryAttemptId(new Uuid(Str::uuid()->toString()));
    }

    public function outboxId(): DeliveryOutboxMessageId
    {
        return new DeliveryOutboxMessageId(new Uuid(Str::uuid()->toString()));
    }
}
