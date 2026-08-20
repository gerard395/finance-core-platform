<?php

declare(strict_types=1);

namespace App\Infrastructure\Relations;

use App\Application\Relations\RelationClassificationIdentityGenerator;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelRelationClassificationIdentityGenerator implements RelationClassificationIdentityGenerator
{
    public function customerId(): CustomerId
    {
        return new CustomerId(new Uuid(Str::uuid()->toString()));
    }

    public function supplierId(): SupplierId
    {
        return new SupplierId(new Uuid(Str::uuid()->toString()));
    }
}
