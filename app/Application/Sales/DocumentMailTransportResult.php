<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Enums\DeliveryAttemptResult;

final readonly class DocumentMailTransportResult
{
    public function __construct(public DeliveryAttemptResult $result, public bool $retryable, public ?string $externalMessageId, public ?string $errorCategory) {}

    public static function accepted(?string $externalMessageId = null): self
    {
        return new self(DeliveryAttemptResult::AcceptedByTransport, false, $externalMessageId, null);
    }

    public static function failed(string $category, bool $retryable): self
    {
        return new self(DeliveryAttemptResult::FailedTransport, $retryable, null, $category);
    }

    public static function unknown(string $category): self
    {
        return new self(DeliveryAttemptResult::OutcomeUnknown, false, null, $category);
    }
}
