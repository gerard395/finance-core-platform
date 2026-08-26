<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\AdministrationBankAccountIdentityGenerator;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelAdministrationBankAccountIdentityGenerator implements AdministrationBankAccountIdentityGenerator
{
    public function next(): AdministrationBankAccountId
    {
        return new AdministrationBankAccountId(new Uuid((string) Str::uuid()));
    }
}
