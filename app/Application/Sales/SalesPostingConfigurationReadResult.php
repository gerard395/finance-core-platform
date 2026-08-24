<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class SalesPostingConfigurationReadResult
{
    private function __construct(
        private SalesPostingConfigurationReadStatus $status,
        private ?SalesPostingConfiguration $configuration,
    ) {}

    public static function success(SalesPostingConfiguration $configuration): self
    {
        return new self(SalesPostingConfigurationReadStatus::Success, $configuration);
    }

    public static function missing(): self
    {
        return new self(SalesPostingConfigurationReadStatus::Missing, null);
    }

    public static function invalidReference(): self
    {
        return new self(SalesPostingConfigurationReadStatus::InvalidReference, null);
    }

    public function status(): SalesPostingConfigurationReadStatus
    {
        return $this->status;
    }

    public function configuration(): ?SalesPostingConfiguration
    {
        return $this->configuration;
    }
}
