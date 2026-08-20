<?php

declare(strict_types=1);

namespace App\Application\Administration;

use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface AdministrationRepository
{
    public function findById(AdministrationId $id): ?Administration;

    public function save(Administration $administration): void;
}
