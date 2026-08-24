<?php

declare(strict_types=1);

namespace App\Infrastructure\Accounting;

use App\Application\Accounting\OpenItemMatchIdentityGenerator;
use App\Domain\Accounting\ValueObjects\OpenItemMatchId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelOpenItemMatchIdentityGenerator implements OpenItemMatchIdentityGenerator
{
    public function next(): OpenItemMatchId
    {
        return new OpenItemMatchId(new Uuid((string) Str::uuid()));
    }
}
