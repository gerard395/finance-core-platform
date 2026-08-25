<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

final readonly class PurchasePostingConfigurationReadResult
{
    /** @param list<PurchasePostingConfigurationInvalidReference> $invalidReferences */
    private function __construct(
        public PurchasePostingConfigurationReadStatus $status,
        public ?PurchasePostingConfiguration $configuration,
        public array $invalidReferences,
    ) {}

    public static function success(PurchasePostingConfiguration $configuration): self
    {
        return new self(PurchasePostingConfigurationReadStatus::Success, $configuration, []);
    }

    public static function missing(): self
    {
        return new self(PurchasePostingConfigurationReadStatus::Missing, null, []);
    }

    /** @param non-empty-list<PurchasePostingConfigurationInvalidReference> $invalidReferences */
    public static function invalidReference(PurchasePostingConfiguration $configuration, array $invalidReferences): self
    {
        return new self(PurchasePostingConfigurationReadStatus::InvalidReference, $configuration, $invalidReferences);
    }
}
