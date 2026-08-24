<?php

declare(strict_types=1);

namespace App\Application\Administration;

final readonly class AdministrationSettings
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}
}
