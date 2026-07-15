<?php

declare(strict_types=1);

namespace App\Domain\Administration\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;

final class Administration
{
    public function __construct(
        public readonly AdministrationId $id,
    ) {}
}
